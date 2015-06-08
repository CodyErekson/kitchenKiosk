<?php
//Initialize the entire PHP side of the system -- should be called before just about anything else runs

namespace KitchenKiosk;

use KitchenKiosk\Utility;
use KitchenKiosk\Database\DB;
use KitchenKiosk\Exception\SystemException;

use DateTime;
use Pimple\Container;
use Noodlehaus\Config;

use Monolog\Logger;
use Monolog\Registry as LoggerRegistry;
use Monolog\ErrorHandler as LoggerErrorHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\BufferHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Processor\PsrLogMessageProcessor;
use Monolog\Processor\UidProcessor;
use Monolog\Processor\ProcessIdProcessor;

class Main {

    private $configFile;

    public $c; // a dependency injection container

    public function __construct($configFile=null){
        $this->configFile = $configFile;
        if ( ( !file_exists($this->configFile) ) && ( $this->configFile != NULL ) ){
            throw new \UnexpectedValueException("Config file " . $this->configFile . " does not exist.");
        }
        //create DIC
        $this->c = new Container();

        // load the config file values; later we'll get the rest from the database
        $this->prepareConfig();

        // populate DIC with function classes
        $this->prepareDependency();

        $this->prepareLogging();

        $this->prepareErrorHandler();

        $this->prepareDatabaseHandler();
        //TODO
        /*
         * mailer
         * database
         * session
         * config
         */
    }

    public function __call($name, $arguments) {
        throw new \BadMethodCallException("Method " . $name . " does not exist: " . print_r($arguments, 1));
    }

    /*
     * Load the config file and store in DIC
     */
    private function prepareConfig(){
        $this->c['configFile'] = $this->configFile;
        $this->c['config'] = function($c){
            return Config::load($c['configFile']);
        };
    }

    /*
     * Populate dependency injector container with known global dependencies
     */
    private function prepareDependency(){
        // Utility functions
        $this->c['general'] = function($c){
            return new Utility\General();
        };
        $this->c['display'] = function($c){
            return new Utility\Display();
        };
        $this->c['security'] = function($c){
            return new Utility\Security();
        };

    }

    /*
     * Create logger, define channel, set up streams, and store in DIC
     */
    private function prepareLogging(){
        $this->c['logger'] = function ($c) {
            $logger = new Logger($c['config']->get("logs.primary_channel")); # Main channel
            # PSR 3 log message formatting for all handlers
            $logger->pushProcessor(new PsrLogMessageProcessor());
            return $logger;
        }; 
        // display logging output to command line if enabled in config
        if ( (bool)$this->c['config']->get("debug.cli") ){
            $this->c->extend('logger', function ($logger, $c) {
                $display = $c['display'];
                $width = getenv('COLUMNS') ?: 60; # Console width from env, or 60 chars.
                $separator = str_repeat('━', $width); # A nice separator line
                $format  = $display->color("bold");
                $format .= $display->color("green") . "[%datetime%]";
                $format .= $display->color("white") . "[%channel%.";
                $format .= $display->color("yellow") . "%level_name%";
                $format .= $display->color("white") . "]";
                $format .= $display->color("blue") . "[UID:%extra.uid%]";
                $format .= $display->color("purple") . "[PID:%extra.process_id%]";
                $format .= $display->color("reset") . ":".PHP_EOL;
                $format .= "%message%".PHP_EOL;
                $format .= $display->color("gray") . $separator . $display->color("reset") . PHP_EOL;
                $handler = new StreamHandler($c['config']->get("logs.stream_handler"));
                $handler->pushProcessor(new UidProcessor(24));
                $handler->pushProcessor(new ProcessIdProcessor());
                $dateFormat = 'H:i:s'; # Just the time for command line
                $allowInlineLineBreaks = (bool)$c['config']->get("logs.allow_inline_linebreaks");
                $formatter = new LineFormatter($format, $dateFormat, $allowInlineLineBreaks);
                $handler->setFormatter($formatter);
                $logger->pushHandler($handler);
                return $logger;
            });
        }
        // primary logging handler; rotating log file inside BufferHandler
        $this->c->extend('logger', function ($logger, $c) {
            $filename = $c['config']->get("directories.root") . $c['config']->get("directories.log") . $c['config']->get("logs.default_log");
            $handler = new RotatingFileHandler($filename, 24, Logger::NOTICE, true, 0644, true);
            $handler->setFilenameFormat('{filename}-{date}', 'Y-m-d');
            $format = "[%datetime%][%channel%][%level_name%][%extra.uid%]: %message%\n";
            $handler->setFormatter(new LineFormatter($format, 'U'));
            $handler->pushProcessor(new UidProcessor(24));
            $logger->pushHandler(new BufferHandler($handler));
            return $logger;
        });
        // finally register the loggers
        $this->c->extend('logger', function ($logger, $c) {
            LoggerRegistry::addLogger($logger);
            LoggerErrorHandler::register($logger);
            return $logger;
        });
    }

    /*
     * Establish error handler
     */
    private function prepareErrorHandler(){
        $this->c['whoops'] = function ($c) {
            // stop PHP from polluting exception messages with html that Whoops escapes and prints.
            ini_set('html_errors', false);
            return new \Whoops\Run; 
        };
        // Pretty page handler
        $this->c->extend('whoops', function ($whoops, $c) {
            $handler = new \Whoops\Handler\PrettyPageHandler();
            $handler->setEditor('sublime');
            $whoops->pushHandler($handler);
            return $whoops;
        });
        // Plain text handler for our logger
        $this->c->extend('whoops', function ($whoops, $c) {
            $handler = new \Whoops\Handler\PlainTextHandler();
            $handler->onlyForCommandLine(false);
            $handler->outputOnlyIfCommandLine(false);
            $handler->loggerOnly(true);
            $handler->setLogger($c['logger']);
            $whoops->pushHandler($handler);
            return $whoops;
        });
        // Responds to AJAX requests with JSON formatted exceptions
        $this->c->extend('whoops', function ($whoops, $c) {
            $handler = new \Whoops\Handler\JsonResponseHandler();
            $handler->onlyForAjaxRequests(true);
            $handler->addTraceToOutput(true);
            $whoops->pushHandler($handler);
            return $whoops;
        });
        $whoops = $this->c['whoops'];
        $whoops->register();
    }

/*
    //start the session
    private function startSession(){
        session_start();
        if (!session_id()) session_regenerate_id();
    }
*/

    /*
     * Initialize a PDO handle and store it in DIC
     */
    private function prepareDatabaseHandle(){
        $this->c['DB'] = function($c){
            $dbhost = $c['config']->get("database.host");
            $dbname = $c['config']->get("database.database");
            $dbuser = $c['config']->get("database.user");
            $dbpass = $c['config']->get("database.password");
            return new \PDO("mysql:host=" . $dbhost . ";dbname=" . $dbname, $dbuser, $dbpass);
        };
        $this->c->extend('DB', function($DB, $c) {
            $DB->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            return $DB;
        });
    }

    /*
    //load configuration from database
    private function loadConfig(){
        $DB = DB::pass();
        $conf = $DB->loadConfig();
        if ( count($conf) > 0 ){
            foreach($conf as $c){
                $this->config->set($c['cluster'] . "." . $c['name'],$c['value']);
            }
        } else {
            throw new SystemException("No database configuration");
        }
    }

    //init PDO singleton and handle
    private function connectDB(){
        try {
            $DBh = DB::obtain(
                $this->config->get('database.host'),
                $this->config->get('database.database'),
                $this->config->get('database.user'),
                $this->config->get('database.password')
            );
            $DB = DB::pass();
            //clear config data from memory
            $this->config->set('database.host',null);
            $this->config->set('database.database',null);
            $this->config->set('database.user',null);
            $this->config->set('database.password',null);
        } catch ( Exception $e ){
            throw new SystemException($e->getMessage());
        }
    }

    */
}

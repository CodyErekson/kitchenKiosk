<?php

namespace KitchenKiosk;

use KitchenKiosk\Utility;
use KitchenKiosk\Database\DB;
use KitchenKiosk\Exception\DatabaseException;

use Pimple\Container;
use Noodlehaus\Config;

use Monolog\Logger;
use Monolog\Registry as LoggerRegistry;
use Monolog\ErrorHandler as LoggerErrorHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\BufferHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Processor\PsrLogMessageProcessor;
use Monolog\Processor\UidProcessor;
use Monolog\Processor\ProcessIdProcessor;

/*
 * Bootstrap entire framework:
 * 1. Get initial configuration options from ini file
 * 2. Instantiate dependency injection container
 * 3. Implement application-wide logging
 * 4. Register error/exception handler
 * 5. Connect to database and store handle
 * 6. Retrieve remainder of configuration from database
 *
 * @class Main
 *
 * @access protected
 */
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
        // set up application logging
        $this->prepareLogging();
        // register error/exception handler
        $this->prepareErrorHandler();
        // initialize database handle
        $this->prepareDatabaseHandler();
        // retrieve config options stored in database
        $this->finalizeConfig();

        //TODO
        /*
         * mailer <-- maybe
         * session <-- maybe
         * JSON web tokens <-- more likely
         * oauth <-- certainly
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
        $this->c->extend('config', function ($config, $c) {
            $config->set("debug.debug", "false");
            return $config;
        });
    }

    /*  
     * Populate dependency injector container with known global dependencies
     */
    private function prepareDependency(){
        // Utility functions
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
                $format  = $display->cliFormat("bold");
                $format .= $display->cliFormat("green") . "[%datetime%]";
                $format .= $display->cliFormat("white") . "[%channel%.";
                $format .= $display->cliFormat("yellow") . "%level_name%";
                $format .= $display->cliFormat("white") . "]";
                $format .= $display->cliFormat("blue") . "[UID:%extra.uid%]";
                $format .= $display->cliFormat("purple") . "[PID:%extra.process_id%]";
                $format .= $display->cliFormat("reset") . ":".PHP_EOL;
                $format .= "%message%".PHP_EOL;
                $format .= $display->cliFormat("gray") . $separator . $display->cliFormat("reset") . PHP_EOL;
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
            //$handler->setEditor('sublime');
            $handler->setEditor('xdebug');
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
     * Initialize a PDO handle and store it in DIC
     */
    private function prepareDatabaseHandler(){
        $this->c['PDO'] = function($c){
            try{
                $dbhost = $c['config']->get("database.host");
                $dbname = $c['config']->get("database.database");
                $dbuser = $c['config']->get("database.user");
                $dbpass = $c['config']->get("database.password");
                return new \PDO("mysql:host=" . $dbhost . ";dbname=" . $dbname, $dbuser, $dbpass);
            } catch ( \PDOException $e ){
                throw new DatabaseException(__CLASS__ . " " . __METHOD__ . " " . $e->getMessage());
            }
        };
        $this->c->extend('PDO', function($PDO, $c) {
            try {
                $PDO->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                return $PDO;
            } catch ( \PDOException $e ){
                throw new DatabaseException(__CLASS__ . " " . __METHOD__ . " " . $e->getMessage());
            }
        });
    }

    /*
    * Load final configuration options from database
    */
    private function finalizeConfig(){
        $config = $this->c['config'];
        $this->c['DB'] = function($c){
            $PDO = $c['PDO'];
            return new Database\DB($PDO);
        };
        $DB = $this->c['DB'];
        try {
            $conf = $DB->loadConfig();
        } catch ( \PDOException $e ){
            throw new DatabaseException(__CLASS__ . " " . __METHOD__ . " " . $e->getMessage());
        }
        if ( count($conf) > 0 ){
            foreach($conf as $e){
                $config->set($e['cluster'] . "." . $e['name'],$e['value']);
            }
            // No longer need database connection information residing in memory
            $config->set('database.host',null);
            $config->set('database.database',null);
            $config->set('database.user',null);
            $config->set('database.password',null);
        } else {
            throw new \UnexpectedValueException("No database configuration");
        }
    }


}

<?php

namespace KitchenKiosk;

use KitchenKiosk\Utility;
use KitchenKiosk\Database\Common;
use KitchenKiosk\Exception\DatabaseException;
use DI\ContainerBuilder;
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

    //public $p; // a dependency injection container

    private $builder;
    public $c; // PHP-DI dependency injection container

    public function __construct($configFile=null){
        $this->configFile = $configFile;
        if ( ( !file_exists($this->configFile) ) && ( $this->configFile != NULL ) ){
            throw new \UnexpectedValueException("Config file " . $this->configFile . " does not exist.");
        }
        //create DIC
        //$this->p = new Container();

        $this->builder = new ContainerBuilder;
        //$cache = new \Doctrine\Common\Cache\ApcCache();
        //$cache->setNamespace('KitchenKiosk');
        //$this->builder->setDefinitionCache($cache);

        // load the config file values; later we'll get the rest from the database
        $this->prepareConfig();
        // populate DIC with function classes
        $this->prepareDependency();
        // set up application logging
        $this->prepareLogging();
        // register error/exception handler
        //$this->prepareErrorHandler();
        // initialize database handle
        $this->prepareDatabaseHandler();

        // The container has been prepared, let's actually build it now
        $this->c = $this->builder->build();

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
        $this->builder->addDefinitions([
            'configFile' => \DI\string($this->configFile),
            'config' => \DI\object('\\Noodlehaus\\Config')->constructor(\DI\get('configFile'))
        ]);
    }

    /*  
     * Populate dependency injector container with known global dependencies
     */
    private function prepareDependency(){
        // Utility functions
        $this->builder->addDefinitions([
            'display' => \DI\object('KitchenKiosk\\Utility\\Display'),
            'security' => \DI\object('KitchenKiosk\\Utility\\Security')
        ]);
    } 

    /*
     * Create logger, define channel, set up streams, and store in DIC
     */
    private function prepareLogging(){
        //use PHP-DI
        $this->builder->addDefinitions([
            'logger' => function($c){
                $logger = new Logger($c->get('config')->get("logs.primary_channel")); # Main channel
                // PSR 3 log message formatting for all handlers
                $logger->pushProcessor(new PsrLogMessageProcessor());
                $filename = $c->get('config')->get("directories.root") . $c->get('config')->get("directories.log") . $c->get('config')->get("logs.default_log");
                $handler = new StreamHandler($filename, Logger::NOTICE, true, 0644, true);
                $format = "[%datetime%][%channel%][%level_name%][%extra.uid%]: %message%\n";
                $handler->setFormatter(new LineFormatter($format, 'Y-m-d H:i:s'));
                $handler->pushProcessor(new UidProcessor(24));
                $logger->pushHandler(new BufferHandler($handler));
                //start CLI
                if ( (bool)$c->get('config')->get("debug.cli") ){
                    $display = $c->get('display');
                    $width = getenv('COLUMNS') ?: 60; # Console width from env, or 60 chars.
                    $separator = str_repeat('━', $width); # A nice separator line
                    $format = $this->c->call(['display','cliFormat'], ['color' => 'bold']);                
                    $format .= $this->c->call(['display','cliFormat'], ['color' => 'green']) . "[%datetime%]";
                    $format .= $this->c->call(['display','cliFormat'], ['color' => 'white']) . "[%channel%.";
                    $format .= $this->c->call(['display','cliFormat'], ['color' => 'yellow']) . "%level_name%";
                    $format .= $this->c->call(['display','cliFormat'], ['color' => 'white']) . "]";
                    $format .= $this->c->call(['display','cliFormat'], ['color' => 'blue']) . "[UID:%extra.uid%]";
                    $format .= $this->c->call(['display','cliFormat'], ['color' => 'purple']) . "[PID:%extra.process_id%]";
                    $format .= $this->c->call(['display','cliFormat'], ['color' => 'reset']) . ":".PHP_EOL;
                    $format .= "%message%".PHP_EOL;
                    $format .= $this->c->call(['display','cliFormat'], ['color' => 'gray']) . $separator . $this->c->call(['display','cliFormat'], ['color' => 'reset']) . PHP_EOL;
                    $handler = new StreamHandler($c->get('config')->get("logs.stream_handler"));
                    $handler->pushProcessor(new UidProcessor(24));
                    $handler->pushProcessor(new ProcessIdProcessor());
                    $dateFormat = 'H:i:s'; // Just the time for command line
                    $allowInlineLineBreaks = (bool)$c->get('config')->get("logs.allow_inline_linebreaks");
                    $formatter = new LineFormatter($format, $dateFormat, $allowInlineLineBreaks);
                    $handler->setFormatter($formatter);
                    $logger->pushHandler($handler);
                }
                //end CLI
                LoggerRegistry::addLogger($logger);
                LoggerErrorHandler::register($logger);
                return $logger;
            }
        ]);
    }

    /*
     * Establish error handler
     */
    /*
    private function prepareErrorHandler(){
        $this->p['whoops'] = function ($p) {
            // stop PHP from polluting exception messages with html that Whoops escapes and prints.
            ini_set('html_errors', false);
            return new \Whoops\Run; 
        };
        // Pretty page handler
        $this->p->extend('whoops', function ($whoops, $p) {
            $handler = new \Whoops\Handler\PrettyPageHandler();
            //$handler->setEditor('sublime');
            $handler->setEditor('xdebug');
            $whoops->pushHandler($handler);
            return $whoops;
        });
        // Plain text handler for our logger
        $this->p->extend('whoops', function ($whoops, $p) {
            $handler = new \Whoops\Handler\PlainTextHandler();
            $handler->onlyForCommandLine(false);
            $handler->outputOnlyIfCommandLine(false);
            $handler->loggerOnly(true);
            $handler->setLogger($p['logger']);
            $whoops->pushHandler($handler);
            return $whoops;
        });
        // Responds to AJAX requests with JSON formatted exceptions
        $this->p->extend('whoops', function ($whoops, $p) {
            $handler = new \Whoops\Handler\JsonResponseHandler();
            $handler->onlyForAjaxRequests(true);
            $handler->addTraceToOutput(true);
            $whoops->pushHandler($handler);
            return $whoops;
        });
        $whoops = $this->p['whoops'];
        $whoops->register();
    }
    */

    /*
     * Initialize a PDO handle and store it in DIC
     */
    private function prepareDatabaseHandler(){
        $this->builder->addDefinitions([
            'PDO' => function($c){
                try{
                    $dbhost = $c->get('config')->get("database.host");
                    $dbname = $c->get('config')->get("database.database");
                    $dbuser = $c->get('config')->get("database.user");
                    $dbpass = $c->get('config')->get("database.password");
                    $PDO = new \PDO("mysql:host=" . $dbhost . ";dbname=" . $dbname, $dbuser, $dbpass);
                    $PDO->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                    return $PDO;
                } catch ( \PDOException $e ){
                    throw new DatabaseException(__CLASS__ . " " . __METHOD__  . " " . $e->getMessage());
                }
            }
        ]);
    }

    /*
    * Load final configuration options from database
    */
    private function finalizeConfig(){
        $common = new Common($this->c->get('PDO'));
        try {
            $conf = $common->loadConfig();
        } catch ( \PDOException $e ){
            throw new DatabaseException(__CLASS__ . " " . __METHOD__  . " " . $e->getMessage());
        }
        if ( count($conf) > 0 ){
            foreach($conf as $e){
                $this->c->call(['config','set'], ['key' => $e['cluster'] . "." . $e['name'], 'value' => $e['value']]);
            }
            // No longer need database connection information residing in memory
            //TODO -- perhaps this is causing the DI empty parameter problems
            //$this->c->call(['config','set'], ['key' => 'database.host', 'value' => null]);
            //$this->c->call(['config','set'], ['key' => 'database.database', 'value' => null]);
            //$this->c->call(['config','set'], ['key' => 'database.user', 'value' => null]);
            //$this->c->call(['config','set'], ['key' => 'database.password', 'value' => null]);
        } else {
            throw new \UnexpectedValueException("No database configuration");
        }
    }


}

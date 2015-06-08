<?php
//Initialize the entire PHP side of the system -- should be called before just about anything else runs

namespace KitchenKiosk;

use Noodlehaus\Config;
use KitchenKiosk\Database\DB;
use KitchenKiosk\Exception\SystemException;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class Initialize {

    private static $singleton;

    private $configFile;
    public $defaultConfigFile = "/config.json";
    public $config; // this will hold the parsed config.ini file data
    public $meta = array(); // various defined configuration variables

    public $log;

    public $events;
    public $loggers = array();

    private function __construct($configFile=null){
        $this->configFile = $configFile;
        if ( $configFile == null ){
            $this->configFile = __DIR__ . $this->defaultConfigFile;
        }
        if ( ( !file_exists($this->configFile) ) && ( $this->configFile != NULL ) ){
            throw new SystemException("Config file " . $this->configFile . " does not exist.");
        }
        $this->config = Config::load($this->configFile);
        $this->connectDB();
        $this->loadConfig();
        $this->createLogger();
        $this->startSession();
        $this->log->addWarning('Warning!');
    }

    public function __call($name, $arguments) {
        throw new SystemException("Method " . $name . " does not exist: " . print_r($arguments, 1));
    }

    public static function obtain($configFile=null) {
        //get this class instance
        if ( !self::$singleton ) {
            self::$singleton = new Initialize($configFile);
        }

        return self::$singleton;
    }

    //start the session
    private function startSession(){
        session_start();
        if (!session_id()) session_regenerate_id();
    }

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

    //create main logger
    private function createLogger(){
        $this->log = new Logger($this->config->get("logs.default_log"));
        $file = $this->config->get("directories.root") . $this->config->get("directories.log") . "/" . $this->config->get("logs.default_log");
        $this->log->pushHandler(new StreamHandler($file, Logger::WARNING));
    }
}

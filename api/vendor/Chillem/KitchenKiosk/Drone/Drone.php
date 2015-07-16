<?php
    // Abstract class for all IoT object "drones"
    // Each drone is a class running on a schedule which retrieves data from a specificied device
    // and stores the data locally
    // In some cases it will be retrieving the data from phant.io

namespace KitchenKiosk\Drone;

abstract class Drone {

    protected $config; // global config object

    public function __construct(\Noodlehaus\Config $config){
        $this->config = $config;
    }

    public function __call($name, $arguments) {
        throw new \BadMethodCallException(__METHOD__ . ": Method " . $name . "does not exist: " . print_r($arguments, 1));
    }

    abstract protected function spawn(\Monolog\Logger $logger);

}

?>

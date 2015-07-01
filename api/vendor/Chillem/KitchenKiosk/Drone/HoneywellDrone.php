<?php

namespace KitchenKiosk\Drone;

use Guzzle\Http\Client;
use Guzzle\Log\PsrLogAdapter;
use Guzzle\Plugin\Log\LogPlugin;
use Guzzle\Log\MessageFormatter;
use Guzzle\Plugin\Cookie\CookiePlugin;
use Guzzle\Plugin\Cookie\CookieJar\ArrayCookieJar;
use Monolog\Logger;
use KitchenKiosk\Exception\DatabaseException;

/*
 * Retrieve current temperature stats from Honeywell thermostat
 */
class HoneywellDrone extends Drone {

    private $client;
    private $cookiePlugin;

    /*
     * Set database handle and config as member variables
     *
     * @param \Noodlehaus\Config $config
     *
     * @param \Monolog\Logger $logger
     *
     */
    public function __construct(\Noodlehaus\Config $config, \Monolog\Logger $logger){
        $this->config = $config;
        //Get the Honeywell connection details from configuration
  
        // Call parent constructor
        parent::__construct($config);

        // Spawn the client object
        $this->spawn($logger);
    }


    /*
     * Create new client object, attach logging, subscribe to global cookie jar, set user agent
     *
     * @param \Monolog\Logger $logger
     *
     */
    protected function spawn(\Monolog\Logger $logger){
        $this->cookiePlugin = new CookiePlugin(new ArrayCookieJar());
        $adapter = new PsrLogAdapter($logger);
        $logPlugin = new LogPlugin($adapter, MessageFormatter::DEBUG_FORMAT);
        $this->client = new Client('https://' . $this->config->get("honeywell.host") . '/');
        $this->client->setUserAgent('Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/28.0.1500.95 Safari/537.36');
        $this->client->addSubscriber($logPlugin);
        $this->client->addSubscriber($this->cookiePlugin);
    }


    /*
     * Login to Honeywell Total Connect Comfort
     */
    public function login(){
        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*//*;q=0.8',
            'Accept-Encoding' => 'sdch',
            'DNT' => '1',
        ];
        $body = [
            'timeOffset' => $this->config->get("honeywell.timeOffset"),
            'UserName' => $this->config->get("honeywell.UserName"),
            'Password' => $this->config->get("honeywell.Password"),
            'RememberMe' => $this->config->get("honeywell.RememberMe")
        ];
        $request = $this->client->post('portal/', $headers, $body);
        $response = $request->send();
        $cookiesArray = $this->cookiePlugin->getCookieJar()->all('https://' . $this->config->get("honeywell.host") . '/');
    }

    /*
     * Get the device ID from current system configuration
     *
     * @return int $deviceId
     */
    public function deviceId(){
       $deviceId = (int)$this->config->get("honeywell.deviceId");
       return $deviceId;
    }

    /*
     * Retrieve all current data from Honeywell Total Connect Comfort
     *
     * @params int $deviceId
     *
     * @params \PDO $PDO Database handle
     *
     * @return string JSON formatted string containing all data returned by TCC API
     */
    public function status($deviceId, \PDO $PDO){
        $headers = [
            'Accept' => '*/*',
            'DNT' => '1',
            'Accept-Encoding' => 'plain',
            'Cache-Control' => 'max-age=0',
            'Accept-Language' => 'en-US,en,q=0.8',
            'Connection' => 'keep-alive',
            'X-Requested-With' => 'XMLHttpRequest'
        ];
        $request = $this->client->get('portal/Device/CheckDataSession/' . (string)$deviceId, $headers, array());
        $response = $request->send();
        $raw = $response->getBody();
        if ( !$jraw = json_decode($raw, true) ){
            throw new \UnexpectedValueException("Unable to retrieve status from Honeywell device with the id " . $deviceId . ".");
        }
        $wanted = [
            'DispTemperature' => $jraw['latestData']['uiData']['DispTemperature'],
            'HeatSetpoint' => $jraw['latestData']['uiData']['HeatSetpoint'],
            'CoolSetpoint' => $jraw['latestData']['uiData']['CoolSetpoint'],
            'DisplayUnits' => $jraw['latestData']['uiData']['DisplayUnits'],
            'VacationHold' => $jraw['latestData']['uiData']['VacationHold'],
            'HeatNextPeriod' => $jraw['latestData']['uiData']['HeatNextPeriod'],
            'CoolNextPeriod' => $jraw['latestData']['uiData']['CoolNextPeriod'],
            'ScheduleHeatSp' => $jraw['latestData']['uiData']['ScheduleHeatSp'],
            'ScheduleCoolSp' => $jraw['latestData']['uiData']['ScheduleCoolSp'],
            'SystemSwitchPosition' => $jraw['latestData']['uiData']['SystemSwitchPosition'],
            'IndoorHumidity' => $jraw['latestData']['uiData']['IndoorHumidity'],
            'OutdoorTemperature' => $jraw['latestData']['uiData']['OutdoorTemperature'],
            'OutdoorHumidity' => $jraw['latestData']['uiData']['OutdoorHumidity'],
            'OutdoorHumidityAvailable' => $jraw['latestData']['uiData']['OutdoorHumidityAvailable'],
            'OutdoorTemperatureAvailable' => $jraw['latestData']['uiData']['OutdoorTemperatureAvailable'],
            'FanMode' => $jraw['latestData']['fanData']['fanMode']
        ];
        $DataTable = new \KitchenKiosk\Database\DataTable($PDO);
        $uid = $DataTable->store($this->config->get("honeywell.origin"), json_encode($wanted));
        $wanted['uid'] = $uid;
        return json_encode($wanted);
    }

}

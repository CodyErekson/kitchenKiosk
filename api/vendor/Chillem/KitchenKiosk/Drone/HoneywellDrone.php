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

    private $DB; // Database handle
    private $client;
    private $cookiePlugin; 

    /*
     * Set database handle and config as member variables
     *
     * @param \Noodlehaus\Config $config
     *
     * @param \KitchenKiosk\Database\DB $DB
     *
     */
    public function __construct(\Noodlehaus\Config $config, \KitchenKiosk\Database\DB $DB, \Monolog\Logger $logger){
        $this->config = $config;
        //Get the Honeywell connection details from configuration
        $this->DB = $DB;
  
        // Call parent constructor
        parent::__construct($config);

        // Spawn the client object
        $this->spawn($logger);
        
        // TODO -- add client to nenber variable. $this->

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
        //$cookie = $cookiesArray[0]->toArray();
    }

}

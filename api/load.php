<?php

    namespace KitchenKiosk;

    ini_set('display_errors','1');

    require_once __DIR__.'/vendor/autoload.php';

    $file = __DIR__ . "/config.json";

    $main = new Main($file); 

    $config = $main->c['config'];

    print_r($config);

    $logger = $main->c['logger'];
    $logger->debug('Monolog is configured.', [$logger]);

    //throw new \Exception("Uh oh!");
?>

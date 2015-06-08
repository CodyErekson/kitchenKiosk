<?php

    namespace KitchenKiosk;

    ini_set('display_errors','1');

    require_once __DIR__.'/vendor/autoload.php';

    $file = __DIR__ . "/config.json";

    $main = new Main($file); 

    //$config = $main->container['config'];

    //print_r($config);

        $test = function ($main){
            $config = $main->container['config'];
            return ( "Log Channel: " . $config->get("logs.primary_channel") . "\n\n" );
        };

        echo $test($main);
        
?>

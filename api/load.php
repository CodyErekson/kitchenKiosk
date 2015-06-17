<?php

    namespace KitchenKiosk;

    // Example of how to include this framework
    // Also used as a handy test playground

    ini_set('display_errors','1');

    require_once __DIR__.'/vendor/autoload.php';

    $file = __DIR__ . "/config.json";

    $main = new Main($file); 

?>

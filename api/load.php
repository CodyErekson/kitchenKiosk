<?php

    namespace KitchenKiosk;

    // Initializer for framework

    ini_set('display_errors','1');

    require_once __DIR__.'/vendor/autoload.php';

    $file = __DIR__ . "/config.json";

    $main = new Main($file); 

    print_r($main->c->get('config'));
?>

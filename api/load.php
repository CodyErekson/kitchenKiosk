<?php

    namespace KitchenKiosk;
    use KitchenKiosk as KK;
    use KitchenKiosk\Utility as util;

    require_once __DIR__.'/vendor/autoload.php';

    $file = __DIR__ . "/config.json";

    $main = Initialize::obtain($file);

    $config = $main->config;
?>

<?php

    use Luracast\Restler\Restler;

    require_once __DIR__ . '/load.php';

    $r = new Restler(true,true);
    $r->addAPIClass('Luracast\\Restler\\Resources');
    $r->addAPIClass('KitchenKiosk\\Utility\\Display','display');
    $r->addAPIClass('KitchenKiosk\\Drone\\HoneywellDrone','drone');
    $r->addAuthenticationClass('KitchenKiosk\\System\\TokenAuth');
    $r->handle();

?>

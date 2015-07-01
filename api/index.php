<?php

    namespace KitchenKiosk;

    use Luracast\Restler\Scope;
    use Luracast\Restler\Restler;

    require_once __DIR__.'/vendor/autoload.php';

    $file = __DIR__ . "/config.json";

    $main = new Main($file);

    $c = $main->c;

    Scope::register('KitchenKiosk\\Drone\\HoneywellDrone', function () use ($c) { 
        return $c->get('KitchenKiosk\\Drone\\HoneywellDrone');
        //return new KitchenKiosk\Drone\HoneywellDrone($c->get('config'), $c->get('logger'));
    });

    $r = new Restler(true,true);
    $r->addAPIClass('Luracast\\Restler\\Resources');
    $r->addAPIClass('KitchenKiosk\\Drone\\HoneywellDrone','drone');

    //TODO -- re-enable these once the DIC is working
    //$r->addAPIClass('KitchenKiosk\\Utility\\Display','display');
    //$r->addAPIClass('KitchenKiosk\\Output\\DataTable','data');
    //$r->addAuthenticationClass('KitchenKiosk\\System\\TokenAuth');
    $r->handle();

?>

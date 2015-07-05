<?php

    use Luracast\Restler\Scope;
    use Luracast\Restler\Restler;
    use KitchenKiosk\Main;
    use KitchenKiosk\Database\DataTable;

    require_once __DIR__.'/vendor/autoload.php';

    $file = __DIR__ . "/config.json";

    $main = new Main($file);

    $c = $main->c;

    Scope::register('KitchenKiosk\\Database\\DataTable', function () use ($c) {
        return new DataTable($c->get('PDO'));
    });

    $r = new Restler(true);
    $r->addAPIClass('Luracast\\Restler\\Resources');
    $r->addAPIClass('KitchenKiosk\\Utility\\Display','display');
    $r->addAPIClass('KitchenKiosk\\Database\\DataTable','data');
    $r->addAuthenticationClass('KitchenKiosk\\System\\TokenAuth');
    $r->handle();

?>

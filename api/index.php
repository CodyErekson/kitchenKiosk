<?php

    use Luracast\Restler\Scope;
    use Luracast\Restler\Restler;
    use Luracast\Restler\Resources;
    use Luracast\Restler\Defaults;
    use KitchenKiosk\Main;
    use KitchenKiosk\Database\DataTable;

    require_once __DIR__.'/vendor/autoload.php';

    Resources::$useFormatAsExtension = false;
    Defaults::$crossOriginResourceSharing = true;
    Defaults::$accessControlAllowOrigin = '*';

    $file = __DIR__ . "/config.json";

    $main = new Main($file);

    $c = $main->c;

    Scope::register('KitchenKiosk\\Database\\DataTable', function () use ($c) {
        return new DataTable($c->get('PDO'));
    });

    $r = new Restler();
    $r->addAPIClass('Luracast\\Restler\\Resources');
    $r->addAPIClass('KitchenKiosk\\Database\\DataTable','data');
    $r->addAPIClass('KitchenKiosk\\Utility\\Display','display');
    $r->addAuthenticationClass('KitchenKiosk\\System\\TokenAuth');
    $r->handle();

?>

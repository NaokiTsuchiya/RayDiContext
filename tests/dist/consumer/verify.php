<?php

declare(strict_types=1);

use NaokiTsuchiya\RayDiContext\AppMeta;
use NaokiTsuchiya\RayDiContext\SingletonWarmer;

require __DIR__ . '/vendor/autoload.php';

$provider = require __DIR__ . '/bootstrap.php';
$meta = AppMeta::fromAppDir(__DIR__, 'prod');

try {
    $context = $provider->get($meta);
    $injector = $context->getInjectorInstance();
    (new SingletonWarmer())($injector);
    $car = $injector->getInstance(ConsumerCarInterface::class);
} catch (Throwable $e) {
    fwrite(STDERR, sprintf("%s: %s\n", $e::class, $e->getMessage()));

    exit(1);
}

if (!$car instanceof ConsumerCar) {
    fwrite(STDERR, sprintf("resolved ConsumerCarInterface to %s, not ConsumerCar\n", $car::class));

    exit(1);
}

fwrite(STDOUT, sprintf("resolved ConsumerCarInterface: %s\n", $car::class));

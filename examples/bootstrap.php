<?php

declare(strict_types=1);

/**
 * Example bootstrap for bin/ray-di-compile.
 *
 * A bootstrap file returns the application's ContextProviderInterface. Copy this
 * file into your app, replace the context classes with your own, then compile with:
 *
 *   php vendor/bin/ray-di-compile path/to/bootstrap.php "$(pwd)" prod
 */

use NaokiTsuchiya\RayDiContext\MapContextProvider;

// Replace App\ProdContext / App\DevContext with your own context classes.
return new MapContextProvider([
    'prod' => App\ProdContext::class,
    'dev' => App\DevContext::class,
]);

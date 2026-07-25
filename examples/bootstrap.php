<?php

declare(strict_types=1);

/**
 * Example bootstrap for bin/compile.php.
 *
 * A bootstrap file returns the application's ContextProviderInterface. Copy this
 * file into your app, replace the context classes with your own, then compile with:
 *
 *   php vendor/bin/compile.php path/to/bootstrap.php my-app "$(pwd)" prod
 */

use NaokiTsuchiya\RayDiContext\MapContextProvider;

// Replace App\ProdContext / App\DevContext with your own context classes.
return new MapContextProvider([
    'prod' => App\ProdContext::class,
    'dev' => App\DevContext::class,
]);

#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Ahead-of-time compile CLI.
 *
 * Usage:
 *   php bin/compile.php <bootstrap> <appDir> <context>
 *
 * The bootstrap file is a PHP script that returns a ContextProviderInterface,
 * for example:
 *
 *   <?php
 *   use NaokiTsuchiya\RayDiContext\MapContextProvider;
 *
 *   return new MapContextProvider([
 *       'prod' => App\ProdContext::class,
 *       'dev'  => App\DevContext::class,
 *   ]);
 *
 * The exit status is the status returned by CompileRunner (0 on success).
 */

use NaokiTsuchiya\RayDiContext\AppMeta;
use NaokiTsuchiya\RayDiContext\CompileRunner;
use NaokiTsuchiya\RayDiContext\ContextProviderInterface;

exit((static function (array $argv): int {
    foreach ([
        __DIR__ . '/../vendor/autoload.php',        // installed as the root package
        __DIR__ . '/../../../autoload.php',          // installed as a dependency (vendor/bin)
    ] as $autoload) {
        if (is_file($autoload)) {
            require $autoload;

            break;
        }
    }

    if (! class_exists(CompileRunner::class)) {
        fwrite(STDERR, "Could not locate the Composer autoloader. Run 'composer install' first.\n");

        return 1;
    }

    [, $bootstrap, $appDir, $context] = $argv + [null, null, null, null];
    if ($bootstrap === null || $appDir === null || $context === null) {
        fwrite(STDERR, "Usage: php bin/compile.php <bootstrap> <appDir> <context>\n");

        return 2;
    }

    if (! is_file($bootstrap)) {
        fwrite(STDERR, sprintf("Bootstrap file not found: %s\n", $bootstrap));

        return 2;
    }

    $provider = require $bootstrap;
    if (! $provider instanceof ContextProviderInterface) {
        fwrite(STDERR, sprintf(
            "Bootstrap file %s must return a %s instance.\n",
            $bootstrap,
            ContextProviderInterface::class,
        ));

        return 2;
    }

    $meta = AppMeta::fromAppDir($appDir, $context);

    return (new CompileRunner($provider))->run($meta);
})($argv));

<?php

declare(strict_types=1);

/**
 * Synthetic, Docker-free check of examples/docker/bin/build-check-support.php's pure functions —
 * invoked by tests/docker-check.sh as `php docker-check-probe.php <support file> <scratch dir>`.
 * Exercises the branches this repo's own real build never reaches: this repo's toy Greeter
 * binding has no AOP interceptor, so filterCompileDirFiles() never has a compileDir file to keep,
 * compileDir is always nested directly under appDir, so relativePath() never climbs, and a real
 * build never has a preload.php write fail.
 */

require $argv[1];

$compileDir = realpath($argv[2]);
if ($compileDir === false) {
    fwrite(STDERR, sprintf("docker-check-probe: compileDir does not exist: %s\n", $argv[2]));

    exit(1);
}

$kept = filterCompileDirFiles($compileDir, ["{$compileDir}/Proxy.php", '/etc/hosts']);
if ($kept !== ["{$compileDir}/Proxy.php"]) {
    fwrite(STDERR, sprintf(
        "filterCompileDirFiles: expected only the compileDir file kept, got %s\n",
        json_encode($kept),
    ));

    exit(1);
}

$nested = relativePath('/app', to: '/app/var/di/prod/Foo.php');
if ($nested !== 'var/di/prod/Foo.php') {
    fwrite(STDERR, sprintf("relativePath: nested case failed: %s\n", $nested));

    exit(1);
}

$nonNested = relativePath('/build', to: '/app/var/di/prod/Foo.php');
if ($nonNested !== '../app/var/di/prod/Foo.php') {
    fwrite(STDERR, sprintf("relativePath: non-nested case failed: %s\n", $nonNested));

    exit(1);
}

$threw = false;
try {
    writePreload($compileDir, appDir: '/no/such/directory/ray-di-context-build-check-probe', loadedClasses: []);
} catch (RuntimeException) {
    $threw = true;
}

if (!$threw) {
    fwrite(STDERR, data: "writePreload: expected a RuntimeException when preload.php cannot be written, none thrown\n");

    exit(1);
}

exit(0);

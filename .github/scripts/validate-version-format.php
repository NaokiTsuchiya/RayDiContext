<?php

declare(strict_types=1);

/**
 * CLI glue for .github/workflows/tag-release.yml's validate job (recovery mode): confirms
 * $argv[1] is a plain "x.y.z" version, printing it back on success. The pure check lives in
 * changelog.php's validateVersionFormat() so tests/changelog-check-probe.php can exercise it
 * without this CLI wrapper.
 *
 * Usage: php validate-version-format.php <version>
 */

require __DIR__ . '/changelog.php';

[, $version] = $argv;

try {
    validateVersionFormat($version);
    echo $version, "\n";
} catch (RuntimeException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

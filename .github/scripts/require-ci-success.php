<?php

declare(strict_types=1);

/**
 * CLI glue for .github/workflows/tag-release.yml's validate job: exits non-zero with a message
 * on stderr unless the given conclusion is "success". The pure logic lives in ci-status.php so
 * tests/ci-status-check-probe.php can exercise it without this CLI wrapper.
 *
 * Usage: php require-ci-success.php <conclusion> (empty string means "no check run found")
 */

require __DIR__ . '/ci-status.php';

$conclusion = $argv[1];

try {
    assertCiConclusionSuccess($conclusion === '' ? null : $conclusion);
} catch (RuntimeException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

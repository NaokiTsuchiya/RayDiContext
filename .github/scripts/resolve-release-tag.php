<?php

declare(strict_types=1);

/**
 * CLI glue for .github/workflows/tag-release.yml's validate job: prints the tag to create, or
 * exits non-zero with a message on stderr. The pure logic lives in changelog.php so
 * tests/changelog-check-probe.php can exercise it without this CLI wrapper.
 *
 * Usage: php resolve-release-tag.php <requested version> <path to CHANGELOG.md>
 */

require __DIR__ . '/changelog.php';

[, $requestedVersion, $changelogPath] = $argv;

$changelog = file_get_contents($changelogPath);
if ($changelog === false) {
    fwrite(STDERR, sprintf("Could not read %s\n", $changelogPath));
    exit(1);
}

try {
    echo resolveReleaseTag($requestedVersion, $changelog), "\n";
} catch (RuntimeException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

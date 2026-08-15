<?php

declare(strict_types=1);

/**
 * CLI glue for .github/workflows/tag-release.yml's validate and tag jobs: prints the requested
 * CHANGELOG.md section's body to STDOUT, or exits non-zero with a message on stderr. The pure
 * logic lives in changelog.php so tests/changelog-check-probe.php can exercise it without this
 * CLI wrapper.
 *
 * Usage: php extract-changelog-section.php <version> <path to CHANGELOG.md>
 */

require __DIR__ . '/changelog.php';

[, $version, $changelogPath] = $argv;

$changelog = file_get_contents($changelogPath);
if ($changelog === false) {
    fwrite(STDERR, sprintf("Could not read %s\n", $changelogPath));
    exit(1);
}

try {
    echo extractChangelogSection($version, $changelog), "\n";
} catch (RuntimeException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

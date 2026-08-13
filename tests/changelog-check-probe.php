<?php

declare(strict_types=1);

/**
 * Synthetic, network-free check of .github/scripts/changelog.php's pure functions — invoked by
 * tests/changelog-check.sh as `php changelog-check-probe.php <support file> <changelog path>
 * <expected latest version>`. Exercises the branches a real release never reaches: a mismatched
 * or "v"-prefixed input, a heading missing its date, and a CHANGELOG with no confirmed section.
 */

require $argv[1];

$changelogPath = $argv[2];
$expected = $argv[3];

$changelog = file_get_contents($changelogPath);
if ($changelog === false) {
    fwrite(STDERR, sprintf("changelog-check-probe: could not read %s\n", $changelogPath));

    exit(1);
}

$latest = latestReleasedVersion($changelog);
if ($latest !== $expected) {
    fwrite(STDERR, sprintf(
        "latestReleasedVersion: expected \"%s\", got %s\n",
        $expected,
        $latest === null ? 'null' : sprintf('"%s"', $latest),
    ));

    exit(1);
}

$resolved = resolveReleaseTag($expected, $changelog);
if ($resolved !== $expected) {
    fwrite(STDERR, sprintf("resolveReleaseTag: expected \"%s\" to resolve to itself, got \"%s\"\n", $expected, $resolved));

    exit(1);
}

$mismatchThrew = false;
try {
    resolveReleaseTag('9.9.9', $changelog);
} catch (RuntimeException) {
    $mismatchThrew = true;
}

if (!$mismatchThrew) {
    fwrite(STDERR, "resolveReleaseTag: expected a RuntimeException for a mismatched version, none thrown\n");

    exit(1);
}

$vPrefixMessage = null;
try {
    resolveReleaseTag('v' . $expected, $changelog);
} catch (RuntimeException $e) {
    $vPrefixMessage = $e->getMessage();
}

if ($vPrefixMessage === null) {
    fwrite(STDERR, "resolveReleaseTag: expected a RuntimeException for a \"v\"-prefixed version, none thrown\n");

    exit(1);
}

if (!str_contains($vPrefixMessage, '"v" prefix')) {
    fwrite(STDERR, sprintf(
        "resolveReleaseTag: \"v\"-prefixed input was rejected for the wrong reason: %s\n",
        $vPrefixMessage,
    ));

    exit(1);
}

$noDateSection = "## [Unreleased]\n\n## [9.9.9]\n\n## [0.2.0] - 2026-08-05\n";
$fromNoDateSection = latestReleasedVersion($noDateSection);
if ($fromNoDateSection !== '0.2.0') {
    fwrite(STDERR, sprintf(
        "latestReleasedVersion: a dateless heading was mistaken for a confirmed section, got %s\n",
        $fromNoDateSection === null ? 'null' : sprintf('"%s"', $fromNoDateSection),
    ));

    exit(1);
}

$unreleasedOnly = "## [Unreleased]\n\n### Added\n\n- nothing yet\n";
$fromUnreleasedOnly = latestReleasedVersion($unreleasedOnly);
if ($fromUnreleasedOnly !== null) {
    fwrite(STDERR, sprintf(
        "latestReleasedVersion: expected null for a CHANGELOG with no confirmed section, got \"%s\"\n",
        $fromUnreleasedOnly,
    ));

    exit(1);
}

exit(0);

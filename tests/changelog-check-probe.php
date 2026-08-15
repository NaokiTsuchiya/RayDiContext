<?php

declare(strict_types=1);

/**
 * Synthetic, network-free check of .github/scripts/changelog.php's pure functions — invoked by
 * tests/changelog-check.sh as `php changelog-check-probe.php <support file> <changelog path>
 * <expected latest version>`. Exercises the branches a real release never reaches: a mismatched
 * or "v"-prefixed input, a heading missing its date, a CHANGELOG with no confirmed section,
 * extractChangelogSection()'s release-notes extraction, and validateVersionFormat()'s rejection
 * of anything but a plain "x.y.z" version (issue #155).
 */

require $argv[1];

$changelogPath = $argv[2];
$expected = $argv[3];

$changelog = file_get_contents($changelogPath);
if ($changelog === false) {
    fwrite(STDERR, sprintf("changelog-check-probe: could not read %s\n", $changelogPath));

    exit(1);
}

/** Independent, regex-based re-implementation checked against extractChangelogSection(). */
function oracleChangelogSection(string $version, string $changelog): string
{
    $pattern = '/^## \[' . preg_quote($version, '/') . '\] - \d{4}-\d{2}-\d{2}$\n(.*?)(?=^## \[|\z)/ms';
    if (preg_match($pattern, $changelog, $matches) !== 1) {
        fwrite(STDERR, sprintf("oracleChangelogSection: no heading found for \"%s\"\n", $version));

        exit(1);
    }

    return trim($matches[1]);
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

$latestSection = extractChangelogSection($expected, $changelog);
$oracleLatestSection = oracleChangelogSection($expected, $changelog);
if ($latestSection !== $oracleLatestSection) {
    fwrite(STDERR, sprintf(
        "extractChangelogSection: \"%s\" section did not match the independent oracle (got %d bytes, expected %d bytes)\n",
        $expected,
        strlen($latestSection),
        strlen($oracleLatestSection),
    ));

    exit(1);
}

$olderVersion = '0.2.0';
if (!str_contains($changelog, "## [{$olderVersion}]")) {
    fwrite(STDERR, sprintf(
        "extractChangelogSection: fixture assumption broke, CHANGELOG.md no longer has a \"%s\" section\n",
        $olderVersion,
    ));

    exit(1);
}

$olderSection = extractChangelogSection($olderVersion, $changelog);
$oracleOlderSection = oracleChangelogSection($olderVersion, $changelog);
if ($olderSection !== $oracleOlderSection) {
    fwrite(STDERR, sprintf(
        "extractChangelogSection: \"%s\" section did not match the independent oracle (got %d bytes, expected %d bytes)\n",
        $olderVersion,
        strlen($olderSection),
        strlen($oracleOlderSection),
    ));

    exit(1);
}

$missingSectionThrew = false;
try {
    extractChangelogSection('9.9.9', $changelog);
} catch (RuntimeException) {
    $missingSectionThrew = true;
}

if (!$missingSectionThrew) {
    fwrite(STDERR, "extractChangelogSection: expected a RuntimeException for a version with no section, none thrown\n");

    exit(1);
}

$vPrefixSectionThrew = false;
try {
    extractChangelogSection('v' . $olderVersion, $changelog);
} catch (RuntimeException) {
    $vPrefixSectionThrew = true;
}

if (!$vPrefixSectionThrew) {
    fwrite(STDERR, "extractChangelogSection: expected a RuntimeException for a \"v\"-prefixed version, none thrown\n");

    exit(1);
}

$unreleasedSectionThrew = false;
try {
    extractChangelogSection('Unreleased', $changelog);
} catch (RuntimeException) {
    $unreleasedSectionThrew = true;
}

if (!$unreleasedSectionThrew) {
    fwrite(STDERR, "extractChangelogSection: expected a RuntimeException for \"Unreleased\", none thrown\n");

    exit(1);
}

$finalVersion = '0.1.0';
if (!str_contains($changelog, "## [{$finalVersion}]")) {
    fwrite(STDERR, sprintf(
        "extractChangelogSection: fixture assumption broke, CHANGELOG.md no longer has a \"%s\" section\n",
        $finalVersion,
    ));

    exit(1);
}

$finalSection = extractChangelogSection($finalVersion, $changelog);
$oracleFinalSection = oracleChangelogSection($finalVersion, $changelog);
if ($finalSection !== $oracleFinalSection) {
    fwrite(STDERR, sprintf(
        "extractChangelogSection: \"%s\" section did not match the independent oracle (got %d bytes, expected %d bytes)\n",
        $finalVersion,
        strlen($finalSection),
        strlen($oracleFinalSection),
    ));

    exit(1);
}

$emptyBodyChangelog = "## [Unreleased]\n\n## [9.9.9] - 2026-08-05\n## [0.2.0] - 2026-08-05\n\nBody.\n";
$emptyBodyThrew = false;
try {
    extractChangelogSection('9.9.9', $emptyBodyChangelog);
} catch (RuntimeException) {
    $emptyBodyThrew = true;
}

if (!$emptyBodyThrew) {
    fwrite(STDERR, "extractChangelogSection: expected a RuntimeException for an empty confirmed section, none thrown\n");

    exit(1);
}

$whitespaceBodyChangelog = "## [Unreleased]\n\n## [9.9.9] - 2026-08-05\n   \t  \n## [0.2.0] - 2026-08-05\n\nBody.\n";
$whitespaceBodyThrew = false;
try {
    extractChangelogSection('9.9.9', $whitespaceBodyChangelog);
} catch (RuntimeException) {
    $whitespaceBodyThrew = true;
}

if (!$whitespaceBodyThrew) {
    fwrite(STDERR, "extractChangelogSection: expected a RuntimeException for a whitespace-only confirmed section, none thrown\n");

    exit(1);
}

$validFormatThrew = null;
try {
    validateVersionFormat($expected);
} catch (RuntimeException $e) {
    $validFormatThrew = $e->getMessage();
}

if ($validFormatThrew !== null) {
    fwrite(STDERR, sprintf("validateVersionFormat: rejected a valid \"x.y.z\" version \"%s\": %s\n", $expected, $validFormatThrew));

    exit(1);
}

foreach (['', '1', '1.2', '1.2.3.4', '1.2.3-beta', 'x.y.z', '1/../../etc/passwd', ' 1.2.3', "{$expected}\n"] as $malformed) {
    $malformedThrew = false;
    try {
        validateVersionFormat($malformed);
    } catch (RuntimeException) {
        $malformedThrew = true;
    }

    if (!$malformedThrew) {
        fwrite(STDERR, sprintf("validateVersionFormat: expected a RuntimeException for malformed version \"%s\", none thrown\n", $malformed));

        exit(1);
    }
}

$formatVPrefixMessage = null;
try {
    validateVersionFormat('v' . $expected);
} catch (RuntimeException $e) {
    $formatVPrefixMessage = $e->getMessage();
}

if ($formatVPrefixMessage === null || !str_contains($formatVPrefixMessage, '"v" prefix')) {
    fwrite(STDERR, sprintf(
        "validateVersionFormat: \"v\"-prefixed input was not rejected with the \"v\" prefix message, got: %s\n",
        $formatVPrefixMessage ?? '<no exception thrown>',
    ));

    exit(1);
}

exit(0);

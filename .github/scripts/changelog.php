<?php

declare(strict_types=1);

/**
 * Pure CHANGELOG.md parsing shared by .github/workflows/tag-release.yml's tag-name resolution,
 * recovery-mode version validation, and issue #155's Release-notes extraction — see
 * tests/changelog-check.sh.
 */

/**
 * @return list<array{version: string|null, date: string|null, body: string}> One entry per
 *     "## [...]" heading, in file order (null version/date for "## [Unreleased]" or a heading
 *     missing the " - YYYY-MM-DD" suffix).
 */
function parseChangelogSections(string $changelog): array
{
    $lines = explode("\n", $changelog);
    $sections = [];
    $current = null;
    $body = [];

    foreach ($lines as $line) {
        if (preg_match('/^## \[(.+?)\](?: - (\d{4}-\d{2}-\d{2}))?$/', $line, $matches) === 1) {
            if ($current !== null) {
                $sections[] = [...$current, 'body' => implode("\n", $body)];
            }

            $heading = $matches[1];
            $date = $matches[2] ?? null;
            $version = $heading !== 'Unreleased' && $date !== null ? $heading : null;
            $current = ['version' => $version, 'date' => $date];
            $body = [];

            continue;
        }

        if ($current !== null) {
            $body[] = $line;
        }
    }

    if ($current !== null) {
        $sections[] = [...$current, 'body' => implode("\n", $body)];
    }

    return $sections;
}

/** Version of the first confirmed ("## [x.y.z] - YYYY-MM-DD") heading, or null if none. */
function latestReleasedVersion(string $changelog): ?string
{
    foreach (parseChangelogSections($changelog) as $section) {
        if ($section['version'] !== null) {
            return $section['version'];
        }
    }

    return null;
}

/** @throws RuntimeException When $version is not a plain "x.y.z" version (three dot-separated non-negative integers). */
function validateVersionFormat(string $version): void
{
    if (str_starts_with($version, 'v')) {
        throw new RuntimeException(sprintf('Version must not carry a "v" prefix: "%s"', $version));
    }

    if (preg_match('/^\d+\.\d+\.\d+$/D', $version) !== 1) {
        throw new RuntimeException(sprintf('Version must be a plain "x.y.z" version: "%s"', $version));
    }
}

/**
 * @throws RuntimeException When $requestedVersion is not a plain "x.y.z" version (see
 *     validateVersionFormat()), or does not match $changelog's latest confirmed version.
 */
function resolveReleaseTag(string $requestedVersion, string $changelog): string
{
    validateVersionFormat($requestedVersion);

    $latest = latestReleasedVersion($changelog);
    if ($latest === null || $requestedVersion !== $latest) {
        throw new RuntimeException(sprintf(
            'Requested version "%s" does not match CHANGELOG.md\'s latest confirmed version (%s)',
            $requestedVersion,
            $latest === null ? '<none found>' : sprintf('"%s"', $latest),
        ));
    }

    return $requestedVersion;
}

/**
 * Body of $changelog's "## [$version] - YYYY-MM-DD" section, with blank lines dropped from the
 * front and back only — internal indentation and trailing Markdown hard-break spaces on content
 * lines are preserved.
 *
 * @throws RuntimeException When $version has no confirmed section, or the section's body is
 *     entirely blank lines.
 */
function extractChangelogSection(string $version, string $changelog): string
{
    foreach (parseChangelogSections($changelog) as $section) {
        if ($section['version'] !== $version) {
            continue;
        }

        $lines = explode("\n", $section['body']);
        while ($lines !== [] && trim($lines[0]) === '') {
            array_shift($lines);
        }
        while ($lines !== [] && trim($lines[count($lines) - 1]) === '') {
            array_pop($lines);
        }

        if ($lines === []) {
            throw new RuntimeException(sprintf(
                'CHANGELOG.md\'s "%s" section has no body (blank between its heading and the next heading)',
                $version,
            ));
        }

        return implode("\n", $lines);
    }

    throw new RuntimeException(sprintf('CHANGELOG.md has no confirmed section for version "%s"', $version));
}

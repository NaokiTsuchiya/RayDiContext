<?php

declare(strict_types=1);

/**
 * Pure CHANGELOG.md parsing shared by .github/workflows/tag-release.yml's tag-name resolution
 * and issue #155's Release-notes generation — see tests/changelog-check.sh.
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

/**
 * @throws RuntimeException When $requestedVersion carries a "v" prefix, or does not match
 *     $changelog's latest confirmed version.
 */
function resolveReleaseTag(string $requestedVersion, string $changelog): string
{
    if (str_starts_with($requestedVersion, 'v')) {
        throw new RuntimeException(sprintf('Version must not carry a "v" prefix: "%s"', $requestedVersion));
    }

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

<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use function preg_match;
use function strlen;
use function strpos;

/**
 * Scans one compiled script for baked path literals
 *
 * A needle occurrence is a violation unless it lies fully inside a compile dir literal:
 * the compile dir is baked into the image together with the scripts, so the compile dir
 * itself — and any path inside it — is allowed. A tmp dir nested under the compile dir
 * extends beyond the literal, so it is still detected.
 *
 * Boundaries are decided byte-wise against the ASCII segment class, so a multi-byte
 * character beside a match counts as a boundary and the occurrence is reported: fail-close,
 * matching the guard. Matching is case-sensitive, like the verbatim comparison it is part
 * of, so "/App/src" does not match a needle of "/app".
 *
 * @internal Used by BakedPathGuard
 */
final class BakedPathScanner
{
    /** A character that continues a path segment; "/" is absent because it ends one */
    private const SEGMENT_CHAR = '/\A[A-Za-z0-9_.\-]\z/';

    /** @var list<array{int, int}> [start, end) ranges of compile dir literals */
    private readonly array $allowedRanges;

    /**
     * @param string           $script     Contents of one compiled script
     * @param non-empty-string $compileDir The baked, read-only compile dir
     */
    public function __construct(
        private readonly string $script,
        string $compileDir,
    ) {
        $this->allowedRanges = $this->compileDirRanges($compileDir);
    }

    /**
     * Returns whether the needle occurs, on a segment boundary, outside every compile dir literal
     *
     * @param non-empty-string $needle
     */
    public function hasBakedPath(string $needle): bool
    {
        $length = strlen($needle);
        $offset = 0;
        while (true) {
            $position = strpos($this->script, $needle, $offset);
            if ($position === false) {
                return false;
            }

            $offset = $position + 1;
            $isPath = $this->isWholePath($position, $length);
            if (!$isPath) {
                continue;
            }

            $contained = $this->isContained($position, $position + $length);
            if (!$contained) {
                return true;
            }
        }
    }

    /**
     * Collects the [start, end) range of every compile dir literal in the script
     *
     * @return list<array{int, int}>
     */
    private function compileDirRanges(string $compileDir): array
    {
        $ranges = [];
        $length = strlen($compileDir);
        $offset = 0;
        while (true) {
            $position = strpos($this->script, $compileDir, $offset);
            if ($position === false) {
                return $ranges;
            }

            $offset = $position + 1;
            $isPath = $this->isWholePath($position, $length);
            if (!$isPath) {
                continue;
            }

            $ranges[] = [$position, $position + $length];
        }
    }

    /**
     * Returns whether the $length bytes at $position span whole path segments
     *
     * A path-segment character on either side means the match runs on into a longer segment
     * and so names a different path: "/app" both in "/appdata" and in "/var/backup/app",
     * "…/prod" in "…/production_logs". "/" is not a segment character, so a match continues
     * to hold when a path nests deeper; neither is the start or the end of the script.
     */
    private function isWholePath(int $position, int $length): bool
    {
        $isPrefixed = $this->isSegmentChar($position - 1);
        $isSuffixed = $this->isSegmentChar($position + $length);

        return !$isPrefixed && !$isSuffixed;
    }

    /**
     * Returns whether the byte at $index continues a path segment
     *
     * An index outside the script does not: it bounds the match. The negative index is
     * spelled out because PHP reads one as an offset from the end of the string.
     */
    private function isSegmentChar(int $index): bool
    {
        if ($index < 0) {
            return false;
        }

        $char = $this->script[$index] ?? '';

        return preg_match(self::SEGMENT_CHAR, $char) === 1;
    }

    /**
     * Returns whether [start, end) lies fully inside an allowed range
     */
    private function isContained(int $start, int $end): bool
    {
        foreach ($this->allowedRanges as [$rangeStart, $rangeEnd]) {
            if ($rangeStart <= $start && $end <= $rangeEnd) {
                return true;
            }
        }

        return false;
    }
}

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
 * itself — and any path inside it — is allowed. An occurrence where the compile dir
 * string merely prefixes a different path ("…/prod" in "…/production_logs") is not a
 * compile dir literal, and a tmp dir nested under the compile dir extends beyond the
 * literal, so both are still detected.
 *
 * @internal Used by BakedPathGuard
 */
final class BakedPathScanner
{
    /** @var list<array{int, int}> [start, end) ranges of compile dir literals */
    private readonly array $allowedRanges;

    /**
     * @param string $script     Contents of one compiled script
     * @param string $compileDir The baked, read-only compile dir
     */
    public function __construct(
        private readonly string $script,
        string $compileDir,
    ) {
        $this->allowedRanges = $this->compileDirRanges($script, $compileDir);
    }

    /**
     * Returns whether the needle occurs outside every compile dir literal
     */
    public function hasBakedPath(string $needle): bool
    {
        if ($needle === '') {
            return false;
        }

        $length = strlen($needle);
        $offset = 0;
        while (true) {
            $position = strpos($this->script, $needle, $offset);
            if ($position === false) {
                return false;
            }

            $offset = $position + 1;
            $contained = $this->isContained($position, $position + $length);
            if (!$contained) {
                return true;
            }
        }
    }

    /** @return list<array{int, int}> */
    private function compileDirRanges(string $script, string $compileDir): array
    {
        $ranges = [];
        $length = strlen($compileDir);
        $offset = 0;
        while (true) {
            $position = strpos($script, $compileDir, $offset);
            if ($position === false) {
                return $ranges;
            }

            $offset = $position + 1;
            // A path-segment character right after means a different path ("…/prod" in
            // "…/production_logs"); "/" does not match and continues inside the compile dir.
            $next = $script[$position + $length] ?? '';
            $isSegmentChar = preg_match('/\A[A-Za-z0-9_.\-]\z/', $next) === 1;
            if ($isSegmentChar) {
                continue;
            }

            $ranges[] = [$position, $position + $length];
        }
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

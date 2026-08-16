<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use function preg_match;
use function strlen;
use function strpos;
use function strtr;

/**
 * Scans one compiled script for baked path literals
 *
 * @internal Used by BakedPathGuard
 */
final class BakedPathScanner
{
    /** A character that continues a path segment; "/" is absent because it ends one */
    private const SEGMENT_CHAR = '/\A[A-Za-z0-9_.\-]\z/';

    /** The two escape sequences a single-quoted PHP literal can carry, mapped back to their bytes */
    private const UNESCAPED = ['\\\\' => '\\', "\\'" => "'"];

    /** The script with every single-quoted literal unescaped */
    private readonly string $script;

    /** @var list<array{int, int}> [start, end) ranges of compile dir literals */
    private readonly array $allowedRanges;

    /**
     * @param string           $script     Contents of one compiled script
     * @param non-empty-string $compileDir The baked, read-only compile dir
     */
    public function __construct(string $script, string $compileDir)
    {
        $this->script = strtr($script, self::UNESCAPED);
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
     */
    private function isWholePath(int $position, int $length): bool
    {
        $isPrefixed = $this->isSegmentChar($position - 1);
        $isSuffixed = $this->isSegmentChar($position + $length);

        return !$isPrefixed && !$isSuffixed;
    }

    /**
     * Returns whether the byte at $index continues a path segment
     */
    private function isSegmentChar(int $index): bool
    {
        if ($index < 0) {
            return false;
        }

        $char = $this->script[$index] ?? '';

        return preg_match(self::SEGMENT_CHAR, $char) === 1;
    }

    /** Returns whether [start, end) lies fully inside an allowed range */
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

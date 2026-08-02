<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext\Support;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * The entries a compile left below the compile dir
 *
 * @internal
 */
final class CompiledTree
{
    /**
     * Asserts every entry is readable by everyone and every script is 0o644, and returns the paths walked
     *
     * @param string $compileDir Directory the compile wrote into
     *
     * @return list<string>
     */
    public static function assertWorldReadable(string $compileDir): array
    {
        $paths = [];
        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($compileDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );
        /** @var SplFileInfo $entry */
        foreach ($entries as $entry) {
            $pathname = $entry->getPathname();
            $mode = Fs::mode($pathname);
            $isDir = $entry->isDir();
            $required = $isDir ? 0o005 : 0o004;
            TestCase::assertSame($required, $mode & $required, $pathname);
            $isScript = !$isDir && $entry->getExtension() === 'php';
            if ($isScript) {
                TestCase::assertSame(0o644, $mode, $pathname);
            }

            $paths[] = $pathname;
        }

        return $paths;
    }
}

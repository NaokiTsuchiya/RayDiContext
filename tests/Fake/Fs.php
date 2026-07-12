<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext\Fake;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function is_dir;
use function rmdir;
use function unlink;

/**
 * Test working-directory helper
 */
final class Fs
{
    /**
     * Removes a directory recursively
     */
    public static function removeDir(string $dir): void
    {
        $exists = is_dir($dir);
        if (!$exists) {
            return;
        }

        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        /** @var SplFileInfo $entry */
        foreach ($entries as $entry) {
            $isLink = $entry->isLink();
            $isDir = $entry->isDir();
            if (!$isLink && $isDir) {
                rmdir($entry->getPathname());
                continue;
            }

            unlink($entry->getPathname());
        }

        rmdir($dir);
    }
}

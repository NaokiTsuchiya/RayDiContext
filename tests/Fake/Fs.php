<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext\Fake;

use FilesystemIterator;
use SplFileInfo;

use function chmod;
use function is_dir;
use function is_executable;
use function is_readable;
use function rmdir;
use function unlink;

/**
 * Test working-directory helper
 */
final class Fs
{
    /**
     * Removes a directory recursively, restoring permissions along the way
     *
     * A test that chmods a directory unreadable to exercise a guard (see CleanerTest,
     * BakedPathGuardTest, ...) leaves that directory behind if the test fails before its
     * own cleanup runs. A RecursiveDirectoryIterator throws a bare UnexpectedValueException
     * on such a directory, which used to fail tearDown itself and, with it, every later
     * test. Each directory is made readable/traversable before it is listed.
     */
    public static function removeDir(string $dir): void
    {
        $exists = is_dir($dir);
        if (!$exists) {
            return;
        }

        $traversable = is_readable($dir) && is_executable($dir);
        if (!$traversable) {
            chmod($dir, permissions: 0o700);
        }

        /** @var SplFileInfo $entry */
        foreach (new FilesystemIterator($dir, FilesystemIterator::SKIP_DOTS) as $entry) {
            $pathname = $entry->getPathname();
            $isLink = $entry->isLink();
            $isDir = $entry->isDir();
            if (!$isLink && $isDir) {
                self::removeDir($pathname);
                continue;
            }

            unlink($pathname);
        }

        rmdir($dir);
    }
}

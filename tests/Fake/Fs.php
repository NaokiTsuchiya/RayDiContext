<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext\Fake;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function chmod;
use function copy;
use function fileperms;
use function is_dir;
use function mkdir;
use function rmdir;
use function rtrim;
use function strlen;
use function substr;
use function unlink;

/**
 * Test working-directory helper
 */
final class Fs
{
    /**
     * Copies a directory recursively, preserving the permission bits of every file
     *
     * The permissions matter for bin/ray-di-compile: a copy that lost the executable bit
     * would not reproduce what an installed package looks like.
     */
    public static function copyDir(string $from, string $to): void
    {
        mkdir($to, permissions: 0o755, recursive: true);
        $rootLength = strlen(rtrim($from, characters: '/')) + 1;
        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );
        /** @var SplFileInfo $entry */
        foreach ($entries as $entry) {
            $source = $entry->getPathname();
            $target = $to . '/' . substr($source, $rootLength);
            $isDir = $entry->isDir();
            if ($isDir) {
                mkdir($target, permissions: 0o755);
                continue;
            }

            copy($source, $target);
            chmod($target, permissions: fileperms($source) & 0o777);
        }
    }

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

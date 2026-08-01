<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext\Support;

use FilesystemIterator;
use SplFileInfo;

use function chmod;
use function copy;
use function dirname;
use function fileperms;
use function is_dir;
use function is_executable;
use function is_readable;
use function mkdir;
use function rmdir;
use function unlink;

/** Test working-directory helper */
final class Fs
{
    /** Returns the permission bits of a path */
    public static function mode(string $path): int
    {
        return (int) fileperms($path) & 0o777;
    }

    /** Removes a directory recursively, restoring permissions along the way */
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

    /** Copies a file to a path whose parent directory may not exist yet */
    public static function copyFile(string $source, string $destination): void
    {
        $parent = dirname($destination);
        $exists = is_dir($parent);
        if (!$exists) {
            mkdir($parent, permissions: 0o755, recursive: true);
        }

        copy($source, $destination);
    }

    /** Copies a directory recursively to a new absolute path, as an image build's COPY would */
    public static function copyDir(string $source, string $destination): void
    {
        mkdir($destination, permissions: 0o755, recursive: true);

        /** @var SplFileInfo $entry */
        foreach (new FilesystemIterator($source, FilesystemIterator::SKIP_DOTS) as $entry) {
            $target = "{$destination}/{$entry->getFilename()}";
            $isDir = $entry->isDir();
            if ($isDir) {
                self::copyDir($entry->getPathname(), $target);
                continue;
            }

            copy($entry->getPathname(), $target);
        }
    }
}

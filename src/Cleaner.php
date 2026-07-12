<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

use function is_dir;
use function mkdir;
use function rmdir;
use function unlink;

/**
 * Empties the compile dir, creating it when missing
 *
 * A recompile must not leave scripts from previous compiles behind: renamed classes
 * and changed bindings would otherwise survive as stale scripts. The directory itself
 * is kept rather than recreated, so a compile dir that is a mount point (a container
 * volume) or a symlinked directory works.
 *
 * @api
 */
final class Cleaner
{
    /** @throws RuntimeException When the compile dir cannot be created or emptied. */
    public function __invoke(string $compileDir): void
    {
        $exists = is_dir($compileDir);
        if ($exists) {
            $this->removeContents($compileDir);

            return;
        }

        $created = mkdir($compileDir, permissions: 0o755, recursive: true);
        $createdConcurrently = is_dir($compileDir);
        if (!$created && !$createdConcurrently) {
            throw new RuntimeException("Failed to create compile dir: {$compileDir}");
        }
    }

    /**
     * Removes every entry inside a directory
     *
     * @throws RuntimeException When an entry cannot be removed.
     */
    private function removeContents(string $dir): void
    {
        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        /** @var SplFileInfo $entry */
        foreach ($entries as $entry) {
            $pathname = $entry->getPathname();
            // A symlink is unlinked, never followed: isDir() resolves to the link target
            $isLink = $entry->isLink();
            $isDir = $entry->isDir();
            $removed = !$isLink && $isDir ? rmdir($pathname) : unlink($pathname);
            if (!$removed) {
                throw new RuntimeException("Failed to remove: {$pathname}");
            }
        }
    }
}

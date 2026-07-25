<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use FilesystemIterator;
use NaokiTsuchiya\RayDiContext\Exception\UnsafeCompileDir;
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
 * Everything below the compile dir is removed without asking, so the dir is verified
 * by CompileDirGuard first: the whole meta is taken rather than a bare path so the
 * guard can compare the compile dir against the app dir.
 *
 * @api
 */
final class Cleaner
{
    /** @param CompileDirGuard $guard Rejects a compile dir that must never be emptied */
    public function __construct(
        private readonly CompileDirGuard $guard = new CompileDirGuard(),
    ) {}

    /**
     * @param AppMeta $meta Application metadata carrying the compile dir to empty
     *
     * @throws UnsafeCompileDir When the compile dir is the filesystem root or holds the app dir.
     * @throws RuntimeException When the compile dir cannot be created or emptied.
     */
    public function __invoke(AppMeta $meta): void
    {
        ($this->guard)($meta);

        $compileDir = $meta->compileDir;
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
            // @codeCoverageIgnoreStart
            // Only reachable via a race (another process removes the entry between the
            // iterator listing it and this call) or a filesystem-level denial that root
            // ignores, so it cannot be triggered deterministically from a test.
            if (!$removed) {
                throw new RuntimeException("Failed to remove: {$pathname}");
            }

            // @codeCoverageIgnoreEnd
        }
    }
}

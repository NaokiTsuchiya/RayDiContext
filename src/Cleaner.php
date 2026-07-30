<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use FilesystemIterator;
use NaokiTsuchiya\RayDiContext\Exception\CompileDirNotWritable;
use NaokiTsuchiya\RayDiContext\Exception\RemoveFailed;
use NaokiTsuchiya\RayDiContext\Exception\UnsafeCompileDir;
use SplFileInfo;
use UnexpectedValueException;

use function is_dir;
use function is_executable;
use function mkdir;
use function restore_error_handler;
use function rmdir;
use function set_error_handler;
use function sprintf;
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
 * by a CompileDirGuardInterface first: the whole meta is taken rather than a bare path
 * so the guard can compare the compile dir against the app dir.
 *
 * @api
 */
final class Cleaner
{
    /** @param CompileDirGuardInterface $guard Rejects a compile dir that must never be emptied */
    public function __construct(
        private readonly CompileDirGuardInterface $guard = new CompileDirGuard(),
    ) {}

    /**
     * @param AppMeta $meta Application metadata carrying the compile dir to empty
     *
     * @throws UnsafeCompileDir When the compile dir is the filesystem root or holds the app dir.
     * @throws CompileDirNotWritable When the compile dir does not exist and cannot be created.
     * @throws RemoveFailed When an entry inside the compile dir cannot be removed.
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

        set_error_handler(static fn(): bool => true);
        try {
            $created = mkdir($compileDir, permissions: 0o755, recursive: true);
        } finally {
            restore_error_handler();
        }

        $createdConcurrently = is_dir($compileDir);
        if (!$created && !$createdConcurrently) {
            throw new CompileDirNotWritable("Failed to create compile dir: {$compileDir}");
        }
    }

    /**
     * Removes every entry inside a directory, descending into subdirectories depth-first
     *
     * The recursion is written out rather than delegated to RecursiveDirectoryIterator +
     * RecursiveIteratorIterator, whose constructors throw a bare UnexpectedValueException
     * when a directory cannot be opened — including one reached mid-traversal, after some
     * entries have already been removed. Written out, each directory is opened through
     * openDir() before anything below it is touched, so an unreadable directory is
     * reported as a RemoveFailed naming it, without removing any of its entries first.
     *
     * @throws RemoveFailed When a directory cannot be read or an entry cannot be removed.
     */
    private function removeContents(string $dir): void
    {
        /** @var SplFileInfo $entry */
        foreach ($this->openDir($dir) as $entry) {
            $pathname = $entry->getPathname();
            // A symlink is unlinked, never followed: isDir() resolves to the link target
            $isLink = $entry->isLink();
            $isDir = $entry->isDir();
            $isRealDir = !$isLink && $isDir;
            if ($isRealDir) {
                $this->removeContents($pathname);
            }

            set_error_handler(static fn(): bool => true);
            try {
                $removed = $isRealDir ? rmdir($pathname) : unlink($pathname);
            } finally {
                restore_error_handler();
            }

            // @codeCoverageIgnoreStart
            // Only reachable via a race (another process removes the entry between the
            // iterator listing it and this call) or a filesystem-level denial that root
            // ignores, so it cannot be triggered deterministically from a test.
            if (!$removed) {
                throw new RemoveFailed("Failed to remove: {$pathname}");
            }

            // @codeCoverageIgnoreEnd
        }
    }

    /**
     * Opens a directory for listing, refusing one whose entries this process cannot reach
     *
     * A directory that cannot be listed fails its FilesystemIterator constructor with a
     * bare UnexpectedValueException, which mago's check-throws cannot see through a
     * constructor and which the declared contract does not mention. A directory that can
     * be listed but not traversed (read granted, execute denied — 0405, 0605, ...) opens
     * without error and instead fails per entry once isLink()/isDir()/getPathname() stat
     * it, so it is checked separately here before any entry is reached.
     *
     * @throws RemoveFailed When the directory cannot be listed or traversed.
     */
    private function openDir(string $dir): FilesystemIterator
    {
        try {
            $entries = new FilesystemIterator($dir);
        } catch (UnexpectedValueException $e) {
            throw new RemoveFailed(
                sprintf('Failed to read directory while emptying compile dir: %s', $dir),
                previous: $e,
            );
        }

        $traversable = is_executable($dir);
        if (!$traversable) {
            throw new RemoveFailed(sprintf('Failed to traverse directory while emptying compile dir: %s', $dir));
        }

        return $entries;
    }
}

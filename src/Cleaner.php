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
 * @internal Built by CompileRunner; an application's knob is CompileDirGuardInterface
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
     *
     * @throws RemoveFailed When a directory cannot be read or an entry cannot be removed.
     */
    private function removeContents(string $dir): void
    {
        /** @var SplFileInfo $entry */
        foreach ($this->openDir($dir) as $entry) {
            $pathname = $entry->getPathname();
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

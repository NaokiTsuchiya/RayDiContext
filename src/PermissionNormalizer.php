<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use FilesystemIterator;
use NaokiTsuchiya\RayDiContext\Exception\ChmodFailed;
use NaokiTsuchiya\RayDiContext\Exception\CompileDirNotFound;
use NaokiTsuchiya\RayDiContext\Exception\CompileDirNotReadable;
use SplFileInfo;
use UnexpectedValueException;

use function chmod;
use function fileperms;
use function is_dir;
use function is_executable;
use function restore_error_handler;
use function set_error_handler;
use function sprintf;

/**
 * Makes the compiled scripts readable by the user that runs the application
 *
 * ray/compiler writes every script through tempnam(), which creates 0600 regardless of
 * umask, so a compile leaves the whole compile dir owner-only. That breaks the deployment
 * this package is built for: build the image as root, COPY the compile dir in, run the
 * container as a non-root user.
 *
 * Subdirectories are descended into: ray/compiler names a script after its dependency
 * index with only the namespace separators replaced, so a qualifier holding a "/" —
 * annotatedWith('a/b') — puts the script in a real directory of its own.
 *
 * @internal
 */
final class PermissionNormalizer
{
    /** Compiled scripts: readable by everyone, writable by the owner */
    private const FILE_MODE = 0o644;

    /** Directories holding compiled scripts: traversable by everyone */
    private const DIR_MODE = 0o755;

    /**
     * Normalizes the compile dir and everything below it
     *
     * The path is checked to be a directory before anything is changed: without that, a
     * chmod lands on a non-directory and only the walk after it fails.
     *
     * @param non-empty-string $compileDir Directory holding the compiled scripts
     *
     * @throws CompileDirNotFound When the path is not an existing directory.
     * @throws CompileDirNotReadable When the compile dir cannot be listed or traversed.
     * @throws ChmodFailed When an entry cannot be made readable.
     */
    public function __invoke(string $compileDir): void
    {
        $isDir = is_dir($compileDir);
        if (!$isDir) {
            throw new CompileDirNotFound(sprintf('Compile dir is not an existing directory: "%s"', $compileDir));
        }

        $this->apply($compileDir, self::DIR_MODE);
        $this->normalizeContents($compileDir);
    }

    /**
     * Normalizes every entry inside a directory, descending into real subdirectories
     *
     * The recursion is written out rather than delegated to RecursiveDirectoryIterator,
     * which follows symlinked directories and would chmod outside the compile dir.
     *
     * @throws CompileDirNotReadable When the directory cannot be listed or traversed.
     * @throws ChmodFailed When an entry cannot be made readable.
     */
    private function normalizeContents(string $dir): void
    {
        /** @var SplFileInfo $entry */
        foreach ($this->openDir($dir) as $entry) {
            // A symlink is left as it is, never followed: chmod resolves to the target
            $isLink = $entry->isLink();
            if ($isLink) {
                continue;
            }

            $pathname = $entry->getPathname();
            $isDir = $entry->isDir();
            if (!$isDir) {
                $this->apply($pathname, self::FILE_MODE);

                continue;
            }

            $this->apply($pathname, self::DIR_MODE);
            $this->normalizeContents($pathname);
        }
    }

    /**
     * Opens a directory for listing, refusing one whose entries this process cannot reach
     *
     * apply() reads the other-class bits while POSIX resolves the owner class first, so a
     * mode whose owner class is the narrower of the two (0005, 0405, ...) satisfies apply()
     * and still denies this process. Missing read then fails as an SPL exception from the
     * iterator; missing execute fails every stat() below instead, one warning per entry.
     *
     * @throws CompileDirNotReadable When the directory cannot be listed or traversed.
     */
    private function openDir(string $dir): FilesystemIterator
    {
        try {
            $entries = new FilesystemIterator($dir);
        } catch (UnexpectedValueException $e) {
            throw new CompileDirNotReadable(sprintf('Compile dir cannot be read: "%s"', $dir), previous: $e);
        }

        $traversable = is_executable($dir);
        if (!$traversable) {
            throw new CompileDirNotReadable(sprintf('Compile dir cannot be traversed: "%s"', $dir));
        }

        return $entries;
    }

    /**
     * Applies a mode unless the entry already grants the world bits that mode carries
     *
     * A compile dir the compiling user does not own — a root-owned 0777 volume, say — is
     * already readable, and chmod on it would fail for no gain.
     *
     * @throws ChmodFailed When the mode cannot be applied.
     */
    private function apply(string $path, int $mode): void
    {
        $required = $mode & 0o007;
        // A failed fileperms() reads as 0, falling through to chmod() so that call reports it.
        $perms = (int) fileperms($path);
        $readable = ($perms & $required) === $required;
        if ($readable) {
            return;
        }

        set_error_handler(static fn(): bool => true);
        try {
            $changed = chmod($path, $mode);
        } finally {
            restore_error_handler();
        }

        // @codeCoverageIgnoreStart
        // chmod() asks for ownership or root, so an entry the process does not own reaches
        // this. No such path carries the same owner and mode across every environment this
        // package supports, so it cannot be reproduced portably — not that it cannot happen.
        if (!$changed) {
            throw new ChmodFailed(sprintf('Failed to set mode %o on: %s', $mode, $path));
        }

        // @codeCoverageIgnoreEnd
    }
}

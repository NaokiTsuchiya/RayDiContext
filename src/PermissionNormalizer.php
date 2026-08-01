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
 * @internal
 */
final class PermissionNormalizer
{
    /** Compiled scripts: world-readable */
    private const FILE_MODE = 0o644;

    /** Directories holding them: world-traversable */
    private const DIR_MODE = 0o755;

    /**
     * Normalizes the compile dir and everything below it
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
     *
     * @throws CompileDirNotReadable When the directory cannot be listed or traversed.
     * @throws ChmodFailed When an entry cannot be made readable.
     */
    private function normalizeContents(string $dir): void
    {
        /** @var SplFileInfo $entry */
        foreach ($this->openDir($dir) as $entry) {
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
     *
     * @throws ChmodFailed When the mode cannot be applied.
     */
    private function apply(string $path, int $mode): void
    {
        $required = $mode & 0o007;
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
        // Reachable — chmod() asks for ownership or root — but not portably: no path carries
        // the same owner and mode across every environment this package supports.
        if (!$changed) {
            throw new ChmodFailed(sprintf('Failed to set mode %o on: %s', $mode, $path));
        }

        // @codeCoverageIgnoreEnd
    }
}

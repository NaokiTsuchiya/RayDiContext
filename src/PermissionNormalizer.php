<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use FilesystemIterator;
use NaokiTsuchiya\RayDiContext\Exception\ChmodFailed;
use SplFileInfo;

use function chmod;
use function fileperms;
use function sprintf;

/**
 * Makes the compiled scripts readable by the user that runs the application
 *
 * Ray.Compiler writes every script through tempnam(), which always creates 0600
 * regardless of umask, and renames it into place — so a compile leaves the whole
 * compile dir owner-only. That breaks the deployment this package is built for:
 * build the image as root, COPY the compile dir in, run the container as a non-root
 * user. The failure surfaces as a bare `require(...): Permission denied` from inside
 * Ray.Compiler, with nothing pointing back at the compile step, so the mode is fixed
 * here rather than left for the reader to discover.
 *
 * An entry that already grants the world bits it needs is left untouched: a compile
 * dir the compiling user does not own (a root-owned 0777 volume, say) is already
 * readable, and chmod on it would fail for no gain. Symlinks are skipped rather than
 * followed, since chmod would apply to the target — outside the compile dir.
 *
 * @api
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
     * @param string $compileDir Directory holding the compiled scripts
     *
     * @throws ChmodFailed When an entry cannot be made readable.
     */
    public function __invoke(string $compileDir): void
    {
        $this->apply($compileDir, self::DIR_MODE);
        $this->normalizeContents($compileDir);
    }

    /**
     * Normalizes every entry inside a directory, descending into real subdirectories
     *
     * @throws ChmodFailed When an entry cannot be made readable.
     */
    private function normalizeContents(string $dir): void
    {
        /** @var SplFileInfo $entry */
        foreach (new FilesystemIterator($dir) as $entry) {
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
     * Applies a mode unless the entry already grants the world bits that mode carries
     *
     * @throws ChmodFailed When the mode cannot be applied.
     */
    private function apply(string $path, int $mode): void
    {
        $required = $mode & 0o007;
        // A failed fileperms() reads as 0 here, which falls through to chmod() and lets
        // that call report the problem instead.
        $perms = (int) fileperms($path);
        $readable = ($perms & $required) === $required;
        if ($readable) {
            return;
        }

        $changed = chmod($path, $mode);
        // @codeCoverageIgnoreStart
        // Only reachable when the process neither owns the entry nor runs as root, which
        // a test cannot set up for itself, or via a race with another process.
        if (!$changed) {
            throw new ChmodFailed(sprintf('Failed to set mode %o on: %s', $mode, $path));
        }

        // @codeCoverageIgnoreEnd
    }
}

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
 * readable, and chmod on it would fail for no gain. That rule reads the other class
 * only, so a directory can satisfy it and still deny this process, which POSIX resolves
 * against the owner class first; any directory that cannot then be listed — the compile
 * dir or one below it — is reported by name instead of being walked into. Symlinks are
 * skipped rather than followed, since chmod would apply to the target — outside the
 * compile dir.
 *
 * Subdirectories are descended into. ray/compiler names a script after its dependency
 * index with only the namespace separators replaced, so a qualifier holding a "/" —
 * annotatedWith('a/b') — makes FilePutContents mkdir a real directory under the compile
 * dir, and the script inside it is written 0600 like any other.
 *
 * This is a workaround for how ray/compiler writes, not a facility to build on: it
 * exists because FilePutContents goes through tempnam(), and CompileRunner builds it
 * where it uses it. If the upstream write ever stops producing 0600, this class goes away.
 *
 * The checks below are defensive rather than input validation: CompileRunner always
 * hands over a directory Cleaner has just created or verified, so what they defend
 * against is the compile dir changing underneath the run — removed, replaced by a file,
 * or made unreadable between the steps — which would otherwise surface as a bare SPL
 * exception, or as a chmod applied to something that is not a compile dir at all.
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
     * The path is verified to be a directory before anything is changed. Without that
     * check a path that is a file gets chmod'ed to 0755 and only then fails, leaving a
     * side effect behind from a call that did not succeed — and it fails as an SPL
     * exception from FilesystemIterator rather than as an exception of this package.
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
     * apply() may have left this directory alone, and that is not the same as this
     * process being able to work in it: apply() reads the other-class bits, while POSIX
     * resolves the owner class first, so a mode whose owner class is narrower than its
     * other class (0005, 0405, 0605, ...) satisfies apply() and still denies the owner.
     * Reading and traversing then fail in two different ways, both of them silent about
     * the real cause:
     *
     * - without read, opening the directory fails as an SPL exception, which mago's
     *   check-throws cannot see through a constructor and which no @throws declares;
     * - without execute, opening succeeds and every stat() of an entry fails instead,
     *   leaking a PHP warning per entry from fileperms() and chmod().
     *
     * Both are turned into one package exception here, before any entry is touched.
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

        // chmod() raises an E_WARNING of its own when the OS refuses. ChmodFailed carries
        // the same information with the path attached, and a warning on top of it would
        // escape a class whose contract is that a failure arrives as one package exception
        // — so the diagnostic is swallowed for this call and the exception is what is left.
        set_error_handler(static fn(): bool => true);
        try {
            $changed = chmod($path, $mode);
        } finally {
            restore_error_handler();
        }

        // @codeCoverageIgnoreStart
        // chmod() asks for ownership or root, so an entry the process does not own reaches
        // this: handing the normalizer /root (Linux) or /var/root (macOS) throws here as an
        // ordinary user. The suite does not do that, because no such path exists with the
        // same owner and mode across every environment this package supports, and a test
        // that skipped itself would take the 100% coverage floor down with it. Ignored
        // because it cannot be reproduced portably, not because it cannot happen.
        if (!$changed) {
            throw new ChmodFailed(sprintf('Failed to set mode %o on: %s', $mode, $path));
        }

        // @codeCoverageIgnoreEnd
    }
}

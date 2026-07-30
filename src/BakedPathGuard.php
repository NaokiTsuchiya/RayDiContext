<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use FilesystemIterator;
use NaokiTsuchiya\RayDiContext\Exception\BakedPathFound;
use NaokiTsuchiya\RayDiContext\Exception\CompileDirNotFound;
use NaokiTsuchiya\RayDiContext\Exception\CompileDirNotReadable;
use NaokiTsuchiya\RayDiContext\Exception\ScriptNotReadable;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use UnexpectedValueException;

use function file_get_contents;
use function is_dir;
use function is_executable;
use function restore_error_handler;
use function set_error_handler;
use function sprintf;

/**
 * Detects appDir and tmpDir literals baked into compiled scripts
 *
 * Paths bound with toInstance() are frozen into the compiled scripts, including every
 * path held by an object bound with toInstance(). Run this guard in CI to catch them
 * before a runtime-dependent path is baked into the image.
 *
 * The compile dir is baked into the image together with the scripts, so a literal that
 * is the compile dir itself — or a path inside it — is allowed. Anything else that
 * contains the appDir or tmpDir string is rejected, including a tmpDir nested under the
 * compile dir (a read-only compile dir can never host the writable tmp dir). The
 * comparison is a verbatim match against the meta strings; spelling variants such as
 * symlink-resolved paths are not recognized.
 *
 * @api
 */
final class BakedPathGuard
{
    /**
     * @param non-empty-string $compileDir
     *
     * @throws CompileDirNotFound When the compile dir is not an existing directory.
     * @throws CompileDirNotReadable When the compile dir, or a directory below it, cannot be
     *                                listed or traversed.
     * @throws BakedPathFound When a compiled script contains an appDir or tmpDir literal.
     * @throws ScriptNotReadable When a compiled script cannot be read.
     */
    public function __invoke(string $compileDir, AppMeta $meta): void
    {
        $isDir = is_dir($compileDir);
        if (!$isDir) {
            throw new CompileDirNotFound(sprintf('Compile dir is not an existing directory: "%s"', $compileDir));
        }

        $traversable = is_executable($compileDir);
        if (!$traversable) {
            throw new CompileDirNotReadable(sprintf('Compile dir cannot be traversed: "%s"', $compileDir));
        }

        try {
            $entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
                $compileDir,
                FilesystemIterator::SKIP_DOTS,
            ));
            /** @var SplFileInfo $entry */
            foreach ($entries as $entry) {
                $extension = $entry->getExtension();
                if ($extension !== 'php') {
                    continue;
                }

                $this->guardScript($entry->getPathname(), $compileDir, $meta);
            }
        } catch (UnexpectedValueException $e) {
            throw new CompileDirNotReadable(sprintf('Compile dir cannot be read: "%s"', $compileDir), previous: $e);
        }
    }

    /**
     * Throws when a single compiled script contains a runtime path literal
     *
     * @param non-empty-string $compileDir
     *
     * @throws BakedPathFound When the script contains an appDir or tmpDir literal.
     * @throws ScriptNotReadable When the script cannot be read.
     */
    private function guardScript(string $path, string $compileDir, AppMeta $meta): void
    {
        // file_get_contents() raises an E_WARNING of its own when it fails.
        // ScriptNotReadable carries the same information with the path attached, and a
        // warning on top of it would escape a class whose contract is that a failure
        // arrives as one package exception — so the diagnostic is swallowed for this call
        // and the exception is what is left.
        set_error_handler(static fn(): bool => true);
        try {
            $script = file_get_contents($path);
        } finally {
            restore_error_handler();
        }

        if ($script === false) {
            throw new ScriptNotReadable("Failed to read compiled script: {$path}");
        }

        $scanner = new BakedPathScanner($script, $compileDir);
        foreach ([$meta->appDir, $meta->tmpDir] as $bakedPath) {
            $hasBakedPath = $scanner->hasBakedPath($bakedPath);
            if ($hasBakedPath) {
                throw new BakedPathFound(sprintf(
                    'Baked path "%s" found in %s. Bind runtime paths through a provider instead of toInstance().',
                    $bakedPath,
                    $path,
                ));
            }
        }
    }
}

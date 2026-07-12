<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use FilesystemIterator;
use NaokiTsuchiya\RayDiContext\Exception\BakedPathFound;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

use function file_get_contents;
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
     * @throws BakedPathFound When a compiled script contains an appDir or tmpDir literal.
     * @throws RuntimeException When a compiled script cannot be read.
     */
    public function __invoke(string $compileDir, AppMeta $meta): void
    {
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
    }

    /**
     * Throws when a single compiled script contains a runtime path literal
     *
     * @throws BakedPathFound When the script contains an appDir or tmpDir literal.
     * @throws RuntimeException When the script cannot be read.
     */
    private function guardScript(string $path, string $compileDir, AppMeta $meta): void
    {
        $script = file_get_contents($path);
        if ($script === false) {
            throw new RuntimeException("Failed to read compiled script: {$path}");
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

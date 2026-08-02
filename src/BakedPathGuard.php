<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use FilesystemIterator;
use NaokiTsuchiya\RayDiContext\Exception\BakedPathFound;
use NaokiTsuchiya\RayDiContext\Exception\CompileDirNotFound;
use NaokiTsuchiya\RayDiContext\Exception\CompileDirNotReadable;
use NaokiTsuchiya\RayDiContext\Exception\InvalidExtraNeedle;
use NaokiTsuchiya\RayDiContext\Exception\ScriptNotReadable;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use UnexpectedValueException;

use function array_filter;
use function file_get_contents;
use function is_dir;
use function is_executable;
use function is_string;
use function restore_error_handler;
use function set_error_handler;
use function sprintf;

/**
 * Detects appDir and tmpDir literals baked into compiled scripts
 *
 * @api
 */
final class BakedPathGuard implements BakedPathGuardInterface
{
    /** @var list<non-empty-string> */
    private readonly array $extraNeedles;

    /**
     * @param list<mixed> $extraNeedles Literals this application knows must not ship, a
     *                                  secret or a host name. Never echoed in a rejection
     *
     * @throws InvalidExtraNeedle When $extraNeedles contains an empty string or a non-string value.
     */
    public function __construct(array $extraNeedles = [])
    {
        $invalidNeedles = array_filter(
            $extraNeedles,
            static fn(mixed $needle): bool => !is_string($needle) || $needle === '',
        );
        if ($invalidNeedles !== []) {
            throw new InvalidExtraNeedle('BakedPathGuard::$extraNeedles must contain only non-empty strings');
        }

        /** @var list<non-empty-string> $extraNeedles */
        $this->extraNeedles = $extraNeedles;
    }

    /** {@inheritDoc} */
    public function __invoke(AppMeta $meta): void
    {
        $compileDir = $meta->compileDir;
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

                $isFile = $entry->isFile();
                if (!$isFile) {
                    throw new ScriptNotReadable(sprintf(
                        'Compiled script path is not a regular file: "%s"',
                        $entry->getPathname(),
                    ));
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

        foreach ($this->extraNeedles as $needle) {
            $hasNeedle = $scanner->hasBakedPath($needle);
            if ($hasNeedle) {
                throw new BakedPathFound(sprintf(
                    'A configured literal was found in %s. '
                    . 'Bind runtime values through a provider instead of toInstance().',
                    $path,
                ));
            }
        }
    }
}

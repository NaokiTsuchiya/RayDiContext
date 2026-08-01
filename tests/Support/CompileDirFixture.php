<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext\Support;

use function chmod;
use function dirname;
use function is_dir;
use function mkdir;
use function uniqid;

/**
 * Working directory holding a compile dir the permission test classes assert the mode of
 *
 * @internal
 */
final class CompileDirFixture
{
    /** @var non-empty-string Per-test working directory */
    public readonly string $baseDir;

    /** @var non-empty-string Directory standing in for the compile dir */
    public readonly string $compileDir;

    /** @param non-empty-string $prefix Names the working directory after the test class using it */
    public function __construct(string $prefix)
    {
        $this->baseDir = dirname(__DIR__) . '/tmp/' . uniqid($prefix, more_entropy: true);
        $this->compileDir = "{$this->baseDir}/di";
        self::makeDir($this->compileDir);
    }

    /** Removes the working directory */
    public function remove(): void
    {
        Fs::removeDir($this->baseDir);
    }

    /** Creates a directory and every missing parent at 0o700, whatever the umask */
    private static function makeDir(string $dir): void
    {
        $parent = dirname($dir);
        $exists = is_dir($parent);
        if (!$exists) {
            self::makeDir($parent);
        }

        mkdir($dir, permissions: 0o700);
        chmod($dir, permissions: 0o700);
    }
}

<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext\Support;

use NaokiTsuchiya\RayDiContext\AppMeta;
use NaokiTsuchiya\RayDiContext\Exception\ExceptionInterface;

use function dirname;
use function mkdir;
use function uniqid;

/**
 * Working directory holding the compile dir the guard test classes scan, with the tmp dir outside
 * the app dir
 *
 * @internal
 */
final class SeparatedDirFixture
{
    /** @var non-empty-string Per-test working directory */
    public readonly string $baseDir;

    /** Meta naming the compile dir and tmp dir explicitly */
    public readonly AppMeta $meta;

    /**
     * @param non-empty-string $prefix Names the working directory after the test class using it
     *
     * @throws ExceptionInterface
     */
    public function __construct(string $prefix)
    {
        $this->baseDir = dirname(__DIR__) . '/tmp/' . uniqid($prefix, more_entropy: true);
        $appDir = "{$this->baseDir}/app";
        $this->meta = new AppMeta($appDir, 'prod', "{$appDir}/var/di/prod", "{$this->baseDir}/rw-tmp");
        mkdir($this->meta->compileDir, permissions: 0o755, recursive: true);
    }

    /** Removes the working directory */
    public function remove(): void
    {
        Fs::removeDir($this->baseDir);
    }
}

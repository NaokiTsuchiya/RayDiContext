<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext\Support;

use NaokiTsuchiya\RayDiContext\AppMeta;
use NaokiTsuchiya\RayDiContext\Exception\ExceptionInterface;

use function dirname;
use function mkdir;
use function uniqid;

/**
 * Working directory shaped like an app dir, with the meta the compile test classes run against
 *
 * @internal
 */
final class AppDirFixture
{
    /** @var non-empty-string Per-test working directory */
    public readonly string $baseDir;

    /** Meta with conventional paths under the app dir */
    public readonly AppMeta $meta;

    /**
     * @param non-empty-string $prefix Names the working directory after the test class using it
     *
     * @throws ExceptionInterface
     */
    public function __construct(string $prefix)
    {
        $this->baseDir = dirname(__DIR__) . '/tmp/' . uniqid($prefix, more_entropy: true);
        $this->meta = AppMeta::fromAppDir("{$this->baseDir}/app", 'prod');
        mkdir($this->meta->tmpDir, permissions: 0o755, recursive: true);
    }

    /** Removes the working directory */
    public function remove(): void
    {
        Fs::removeDir($this->baseDir);
    }
}

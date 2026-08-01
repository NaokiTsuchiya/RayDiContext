<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\ExceptionInterface;
use NaokiTsuchiya\RayDiContext\Exception\InvalidAppMeta;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

/** Covers the public constructor; AppMetaFromAppDirTest covers the fromAppDir() factory */
#[CoversClass(AppMeta::class)]
final class AppMetaTest extends TestCase
{
    /**
     * Keeps constructor arguments as-is
     *
     * The constructor restricts context to non-empty: it is only a lookup key here, not a
     * path fragment, so "prod:staging" is still accepted.
     *
     * @throws ExceptionInterface
     */
    #[Test]
    public function construct(): void
    {
        $meta = new AppMeta('/path/to/app', 'prod:staging', '/opt/di', '/tmp/rw');

        static::assertSame('/path/to/app', $meta->appDir);
        static::assertSame('prod:staging', $meta->context);
        static::assertSame('/opt/di', $meta->compileDir);
        static::assertSame('/tmp/rw', $meta->tmpDir);
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function constructTrimsTrailingSlashes(): void
    {
        $meta = new AppMeta('/path/to/app/', 'prod', '/opt/di/', '/tmp/rw/');

        static::assertSame('/path/to/app', $meta->appDir);
        static::assertSame('/opt/di', $meta->compileDir);
        static::assertSame('/tmp/rw', $meta->tmpDir);
    }

    /** @throws ExceptionInterface */
    #[TestWith(['', 'prod', '/opt/di', '/tmp/rw'])]
    #[TestWith(['/path/to/app', '', '/opt/di', '/tmp/rw'])]
    #[TestWith(['/path/to/app', 'prod', '', '/tmp/rw'])]
    #[TestWith(['/path/to/app', 'prod', '/opt/di', ''])]
    #[Test]
    public function rejectsEmptyField(string $appDir, string $context, string $compileDir, string $tmpDir): void
    {
        $this->expectException(InvalidAppMeta::class);

        new AppMeta($appDir, $context, $compileDir, $tmpDir);
    }

    /**
     * Both guards read $meta->appDir verbatim whichever entry point produced it, so the
     * invariant belongs to the type rather than to one factory.
     *
     * @throws ExceptionInterface
     */
    #[TestWith(['app'])]
    #[TestWith(['./app'])]
    #[TestWith(['.'])]
    #[Test]
    public function rejectsNonAbsoluteAppDir(string $appDir): void
    {
        $this->expectException(InvalidAppMeta::class);
        $this->expectExceptionMessage('must be an absolute path');

        new AppMeta($appDir, 'prod', '/opt/di', '/tmp/rw');
    }

    /** @throws ExceptionInterface */
    #[TestWith(['/opt/di', '/opt/di'])]
    #[TestWith(['/opt/di/', '/opt/di'])]
    #[TestWith(['/opt/di', '/opt/di///'])]
    #[Test]
    public function rejectsCompileDirEqualToTmpDir(string $compileDir, string $tmpDir): void
    {
        $this->expectException(InvalidAppMeta::class);
        $this->expectExceptionMessage('must be different directories');

        new AppMeta('/path/to/app', 'prod', $compileDir, $tmpDir);
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function allowsTmpDirNestedUnderCompileDir(): void
    {
        $meta = new AppMeta('/path/to/app', 'prod', '/opt/di', '/opt/di/tmp');

        static::assertSame('/opt/di', $meta->compileDir);
        static::assertSame('/opt/di/tmp', $meta->tmpDir);
    }
}

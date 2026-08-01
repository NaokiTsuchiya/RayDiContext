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
    /** @throws ExceptionInterface */
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
    #[TestWith(['', 'prod', '/opt/di', '/tmp/rw', 'AppMeta::$appDir must not be empty'], 'empty appDir')]
    #[TestWith(['/path/to/app', '', '/opt/di', '/tmp/rw', 'AppMeta::$context must not be empty'], 'empty context')]
    #[TestWith(['/path/to/app', 'prod', '', '/tmp/rw', 'AppMeta::$compileDir must not be empty'], 'empty compileDir')]
    #[TestWith(['/path/to/app', 'prod', '/opt/di', '', 'AppMeta::$tmpDir must not be empty'], 'empty tmpDir')]
    #[Test]
    public function rejectsEmptyField(
        string $appDir,
        string $context,
        string $compileDir,
        string $tmpDir,
        string $message,
    ): void {
        $this->expectException(InvalidAppMeta::class);
        $this->expectExceptionMessage($message);

        new AppMeta($appDir, $context, $compileDir, $tmpDir);
    }

    /** @throws ExceptionInterface */
    #[TestWith(['app'], 'bare relative segment')]
    #[TestWith(['./app'], 'explicitly relative path')]
    #[TestWith(['.'], 'current directory')]
    #[Test]
    public function rejectsNonAbsoluteAppDir(string $appDir): void
    {
        $this->expectException(InvalidAppMeta::class);
        $this->expectExceptionMessage('must be an absolute path');

        new AppMeta($appDir, 'prod', '/opt/di', '/tmp/rw');
    }

    /** @throws ExceptionInterface */
    #[TestWith(['/opt/di', '/opt/di'], 'identical spelling')]
    #[TestWith(['/opt/di/', '/opt/di'], 'equal after trimming the compile dir trailing slash')]
    #[TestWith(['/opt/di', '/opt/di///'], 'equal after trimming repeated tmp dir trailing slashes')]
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

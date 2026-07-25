<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\InvalidAppMeta;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

use function putenv;

#[CoversClass(AppMeta::class)]
final class AppMetaTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    protected function tearDown(): void
    {
        putenv('APP_COMPILE_DIR');
        putenv('APP_TMP_DIR');
    }

    /**
     * Keeps constructor arguments as-is
     *
     * @throws InvalidAppMeta
     */
    #[Test]
    public function construct(): void
    {
        $meta = new AppMeta('/path/to/app', 'prod', '/opt/di', '/tmp/rw');

        static::assertSame('/path/to/app', $meta->appDir);
        static::assertSame('prod', $meta->context);
        static::assertSame('/opt/di', $meta->compileDir);
        static::assertSame('/tmp/rw', $meta->tmpDir);
    }

    /**
     * Trailing slashes on appDir/compileDir/tmpDir are trimmed by the public constructor too
     *
     * @throws InvalidAppMeta
     */
    #[Test]
    public function constructTrimsTrailingSlashes(): void
    {
        $meta = new AppMeta('/path/to/app/', 'prod', '/opt/di/', '/tmp/rw/');

        static::assertSame('/path/to/app', $meta->appDir);
        static::assertSame('/opt/di', $meta->compileDir);
        static::assertSame('/tmp/rw', $meta->tmpDir);
    }

    /**
     * Every field must be non-empty
     *
     * @throws InvalidAppMeta
     */
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
     * A context containing a path separator or a parent-dir reference is rejected
     *
     * @throws InvalidAppMeta
     */
    #[TestWith(['prod/staging'])]
    #[TestWith(['../prod'])]
    #[TestWith(['pro..d'])]
    #[Test]
    public function rejectsUnsafeContext(string $context): void
    {
        $this->expectException(InvalidAppMeta::class);

        new AppMeta('/path/to/app', $context, '/opt/di', '/tmp/rw');
    }

    /**
     * Falls back to conventional paths under the app dir
     *
     * @throws InvalidAppMeta
     */
    #[Test]
    public function fromAppDirDefaults(): void
    {
        $meta = AppMeta::fromAppDir('/path/to/app', 'prod');

        static::assertSame('/path/to/app', $meta->appDir);
        static::assertSame('prod', $meta->context);
        static::assertSame('/path/to/app/var/di/prod', $meta->compileDir);
        static::assertSame('/path/to/app/var/tmp/prod', $meta->tmpDir);
    }

    /**
     * APP_COMPILE_DIR and APP_TMP_DIR override the defaults independently
     *
     * @throws InvalidAppMeta
     */
    #[Test]
    public function fromAppDirEnvOverride(): void
    {
        putenv('APP_COMPILE_DIR=/opt/di');
        putenv('APP_TMP_DIR=/tmp/rw');

        $meta = AppMeta::fromAppDir('/path/to/app', 'prod');

        static::assertSame('/opt/di', $meta->compileDir);
        static::assertSame('/tmp/rw', $meta->tmpDir);
    }

    /**
     * An empty env value is treated as unset
     *
     * @throws InvalidAppMeta
     */
    #[Test]
    public function fromAppDirEmptyEnvFallsBack(): void
    {
        putenv('APP_COMPILE_DIR=');
        putenv('APP_TMP_DIR=');

        $meta = AppMeta::fromAppDir('/path/to/app', 'prod');

        static::assertSame('/path/to/app/var/di/prod', $meta->compileDir);
        static::assertSame('/path/to/app/var/tmp/prod', $meta->tmpDir);
    }

    /**
     * Overriding only the compile dir leaves the tmp dir at its default
     *
     * @throws InvalidAppMeta
     */
    #[Test]
    public function fromAppDirPartialEnvOverride(): void
    {
        putenv('APP_COMPILE_DIR=/opt/di');

        $meta = AppMeta::fromAppDir('/path/to/app', 'prod');

        static::assertSame('/opt/di', $meta->compileDir);
        static::assertSame('/path/to/app/var/tmp/prod', $meta->tmpDir);
    }

    /**
     * Trailing slashes are trimmed so paths compare verbatim against baked literals
     *
     * @throws InvalidAppMeta
     */
    #[Test]
    public function fromAppDirTrimsTrailingSlashes(): void
    {
        putenv('APP_TMP_DIR=/tmp/rw/');

        $meta = AppMeta::fromAppDir('/path/to/app/', 'prod');

        static::assertSame('/path/to/app', $meta->appDir);
        static::assertSame('/path/to/app/var/di/prod', $meta->compileDir);
        static::assertSame('/tmp/rw', $meta->tmpDir);
    }
}

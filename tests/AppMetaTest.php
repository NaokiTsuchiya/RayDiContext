<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
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
     */
    #[Test]
    public function construct(): void
    {
        $meta = new AppMeta('my-app', '/path/to/app', '/opt/di', '/tmp/rw');

        static::assertSame('my-app', $meta->name);
        static::assertSame('/path/to/app', $meta->appDir);
        static::assertSame('/opt/di', $meta->compileDir);
        static::assertSame('/tmp/rw', $meta->tmpDir);
    }

    /**
     * Falls back to conventional paths under the app dir
     */
    #[Test]
    public function fromAppDirDefaults(): void
    {
        $meta = AppMeta::fromAppDir('my-app', '/path/to/app', 'prod');

        static::assertSame('my-app', $meta->name);
        static::assertSame('/path/to/app', $meta->appDir);
        static::assertSame('/path/to/app/var/di/prod', $meta->compileDir);
        static::assertSame('/path/to/app/var/tmp/prod', $meta->tmpDir);
    }

    /**
     * APP_COMPILE_DIR and APP_TMP_DIR override the defaults independently
     */
    #[Test]
    public function fromAppDirEnvOverride(): void
    {
        putenv('APP_COMPILE_DIR=/opt/di');
        putenv('APP_TMP_DIR=/tmp/rw');

        $meta = AppMeta::fromAppDir('my-app', '/path/to/app', 'prod');

        static::assertSame('/opt/di', $meta->compileDir);
        static::assertSame('/tmp/rw', $meta->tmpDir);
    }

    /**
     * An empty env value is treated as unset
     */
    #[Test]
    public function fromAppDirEmptyEnvFallsBack(): void
    {
        putenv('APP_COMPILE_DIR=');
        putenv('APP_TMP_DIR=');

        $meta = AppMeta::fromAppDir('my-app', '/path/to/app', 'prod');

        static::assertSame('/path/to/app/var/di/prod', $meta->compileDir);
        static::assertSame('/path/to/app/var/tmp/prod', $meta->tmpDir);
    }

    /**
     * Overriding only the compile dir leaves the tmp dir at its default
     */
    #[Test]
    public function fromAppDirPartialEnvOverride(): void
    {
        putenv('APP_COMPILE_DIR=/opt/di');

        $meta = AppMeta::fromAppDir('my-app', '/path/to/app', 'prod');

        static::assertSame('/opt/di', $meta->compileDir);
        static::assertSame('/path/to/app/var/tmp/prod', $meta->tmpDir);
    }

    /**
     * Trailing slashes are trimmed so paths compare verbatim against baked literals
     */
    #[Test]
    public function fromAppDirTrimsTrailingSlashes(): void
    {
        putenv('APP_TMP_DIR=/tmp/rw/');

        $meta = AppMeta::fromAppDir('my-app', '/path/to/app/', 'prod');

        static::assertSame('/path/to/app', $meta->appDir);
        static::assertSame('/path/to/app/var/di/prod', $meta->compileDir);
        static::assertSame('/tmp/rw', $meta->tmpDir);
    }
}

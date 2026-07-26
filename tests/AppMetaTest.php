<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\InvalidAppMeta;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(AppMeta::class)]
final class AppMetaTest extends TestCase
{
    /**
     * Keeps constructor arguments as-is
     *
     * The constructor has no character restriction on context beyond non-empty: it is
     * only a lookup key here (e.g. for MapContextProvider), not necessarily a path
     * fragment, so "prod:staging" — not a safe path segment — is still accepted.
     *
     * @throws InvalidAppMeta
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
     * Falls back to conventional paths under the app dir
     *
     * A context that is not a single conventional path segment — containing "/", as a
     * namespaced class-string would with "\" — is still accepted: it is concatenated,
     * not resolved, so it only nests an extra directory level rather than escaping
     * anywhere.
     *
     * @throws InvalidAppMeta
     */
    #[Test]
    public function fromAppDirDefaults(): void
    {
        $context = 'App/ProdContext';
        $meta = AppMeta::fromAppDir('/path/to/app', $context);

        static::assertSame('/path/to/app', $meta->appDir);
        static::assertSame($context, $meta->context);
        static::assertSame("/path/to/app/var/di/{$context}", $meta->compileDir);
        static::assertSame("/path/to/app/var/tmp/{$context}", $meta->tmpDir);
    }

    /**
     * A context containing ".." is rejected: the OS resolves it as a parent-dir
     * traversal wherever the interpolated compileDir/tmpDir is later used
     *
     * @throws InvalidAppMeta
     */
    #[TestWith(['../prod'])]
    #[TestWith(['pro..d'])]
    #[TestWith(['prod/../../etc'])]
    #[Test]
    public function fromAppDirRejectsParentDirTraversal(string $context): void
    {
        $this->expectException(InvalidAppMeta::class);

        AppMeta::fromAppDir('/path/to/app', $context);
    }

    /**
     * Explicit compileDir/tmpDir override the conventional defaults independently
     *
     * fromAppDir() no longer reads the environment itself; a caller such as
     * bin/ray-di-compile reads APP_COMPILE_DIR/APP_TMP_DIR and passes the result in.
     *
     * @throws InvalidAppMeta
     */
    #[Test]
    public function fromAppDirOverride(): void
    {
        $meta = AppMeta::fromAppDir('/path/to/app', 'prod', '/opt/di', '/tmp/rw');

        static::assertSame('/opt/di', $meta->compileDir);
        static::assertSame('/tmp/rw', $meta->tmpDir);
    }

    /**
     * Overriding only the compile dir leaves the tmp dir at its default
     *
     * @throws InvalidAppMeta
     */
    #[Test]
    public function fromAppDirPartialOverride(): void
    {
        $meta = AppMeta::fromAppDir('/path/to/app', 'prod', compileDir: '/opt/di');

        static::assertSame('/opt/di', $meta->compileDir);
        static::assertSame('/path/to/app/var/tmp/prod', $meta->tmpDir);
    }

    /**
     * Trailing slashes are trimmed on both the conventional default and an override
     *
     * @throws InvalidAppMeta
     */
    #[Test]
    public function fromAppDirTrimsTrailingSlashes(): void
    {
        $meta = AppMeta::fromAppDir('/path/to/app/', 'prod', tmpDir: '/tmp/rw/');

        static::assertSame('/path/to/app', $meta->appDir);
        static::assertSame('/path/to/app/var/di/prod', $meta->compileDir);
        static::assertSame('/tmp/rw', $meta->tmpDir);
    }
}

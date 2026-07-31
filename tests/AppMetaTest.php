<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\InvalidAppMeta;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

/**
 * Covers the public constructor; AppMetaFromAppDirTest covers the fromAppDir() factory
 */
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
     * appDir must be absolute through the public constructor too, not just fromAppDir()
     *
     * BakedPathGuard and CompileDirGuard both read $meta->appDir verbatim regardless of
     * which entry point produced it, so a relative appDir is just as unsafe here as it is
     * through fromAppDir() — the invariant belongs to the type, not to one factory.
     *
     * @throws InvalidAppMeta
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

    /**
     * compileDir and tmpDir must be different directories
     *
     * This is the one shape BakedPathGuard cannot see: it allows a literal that lies inside a
     * compileDir literal, so when the two paths are the same string every tmpDir occurrence is
     * also a compileDir occurrence and the tmpDir check passes on scripts it exists to reject.
     * Trailing slashes are trimmed before the comparison, so they cannot spell around it.
     *
     * @throws InvalidAppMeta
     */
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

    /**
     * A tmp dir merely nested under the compile dir is left to BakedPathGuard
     *
     * That shape extends past the allowed compileDir literal, so the guard still reports it;
     * refusing it here would only remove a working defence.
     *
     * @throws InvalidAppMeta
     */
    #[Test]
    public function allowsTmpDirNestedUnderCompileDir(): void
    {
        $meta = new AppMeta('/path/to/app', 'prod', '/opt/di', '/opt/di/tmp');

        static::assertSame('/opt/di', $meta->compileDir);
        static::assertSame('/opt/di/tmp', $meta->tmpDir);
    }
}

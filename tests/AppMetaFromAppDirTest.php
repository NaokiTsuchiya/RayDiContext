<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\InvalidAppMeta;
use NaokiTsuchiya\RayDiContext\Fake\Fs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

use function chdir;
use function getcwd;
use function mkdir;
use function uniqid;

/**
 * Covers the fromAppDir() factory; AppMetaTest covers the public constructor
 *
 * The factory resolves appDir with realpath(), so unlike the constructor it needs an app
 * dir that exists on disk.
 */
#[CoversClass(AppMeta::class)]
final class AppMetaFromAppDirTest extends TestCase
{
    /** @var non-empty-string Per-test working directory */
    private string $baseDir;

    /** @var non-empty-string Existing app dir to resolve */
    private string $appDir;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->baseDir = __DIR__ . '/tmp/' . uniqid('meta_', more_entropy: true);
        $this->appDir = "{$this->baseDir}/app";
        mkdir($this->appDir, permissions: 0o755, recursive: true);
    }

    /**
     * {@inheritDoc}
     */
    protected function tearDown(): void
    {
        Fs::removeDir($this->baseDir);
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
    public function defaults(): void
    {
        $context = 'App/ProdContext';
        $meta = AppMeta::fromAppDir($this->appDir, $context);

        static::assertSame($this->appDir, $meta->appDir);
        static::assertSame($context, $meta->context);
        static::assertSame("{$this->appDir}/var/di/{$context}", $meta->compileDir);
        static::assertSame("{$this->appDir}/var/tmp/{$context}", $meta->tmpDir);
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
    public function rejectsParentDirTraversal(string $context): void
    {
        $this->expectException(InvalidAppMeta::class);

        AppMeta::fromAppDir($this->appDir, $context);
    }

    /**
     * Explicit compileDir/tmpDir override the conventional defaults independently
     *
     * fromAppDir() no longer reads the environment itself; a caller such as
     * bin/ray-di-compile passes the result in. Only appDir is resolved, so an override
     * still reaches AppMeta verbatim — compile-time and runtime have to agree on the
     * literal, and resolving it here would silently change it under a symlink.
     *
     * @throws InvalidAppMeta
     */
    #[Test]
    public function override(): void
    {
        $meta = AppMeta::fromAppDir($this->appDir, 'prod', '/opt/di', '/tmp/rw');

        static::assertSame('/opt/di', $meta->compileDir);
        static::assertSame('/tmp/rw', $meta->tmpDir);
    }

    /**
     * Overriding only the compile dir leaves the tmp dir at its default
     *
     * @throws InvalidAppMeta
     */
    #[Test]
    public function partialOverride(): void
    {
        $meta = AppMeta::fromAppDir($this->appDir, 'prod', compileDir: '/opt/di');

        static::assertSame('/opt/di', $meta->compileDir);
        static::assertSame("{$this->appDir}/var/tmp/prod", $meta->tmpDir);
    }

    /**
     * Trailing slashes are trimmed on both the conventional default and an override
     *
     * @throws InvalidAppMeta
     */
    #[Test]
    public function trimsTrailingSlashes(): void
    {
        $meta = AppMeta::fromAppDir("{$this->appDir}/", 'prod', tmpDir: '/tmp/rw/');

        static::assertSame($this->appDir, $meta->appDir);
        static::assertSame("{$this->appDir}/var/di/prod", $meta->compileDir);
        static::assertSame('/tmp/rw', $meta->tmpDir);
    }

    /**
     * A relative appDir is resolved to an absolute path
     *
     * Left relative, it reaches BakedPathGuard as a needle that matches nearly every
     * literal — "." matches all of them — and fails the compile with a message that reads
     * as a baked path rather than as a bad argument.
     *
     * @throws InvalidAppMeta
     */
    #[Test]
    public function resolvesRelativeAppDir(): void
    {
        $cwd = getcwd();
        static::assertNotFalse($cwd);
        chdir($this->appDir);

        try {
            $meta = AppMeta::fromAppDir('.', 'prod');
        } finally {
            chdir($cwd);
        }

        static::assertSame($this->appDir, $meta->appDir);
        static::assertSame("{$this->appDir}/var/di/prod", $meta->compileDir);
    }

    /**
     * An appDir that cannot be resolved is rejected
     *
     * A missing one would fail the compile later anyway, further away from the cause. An
     * empty one is checked separately because realpath('') answers with the working
     * directory rather than failing, which would turn an unset argument into a
     * plausible-looking app dir.
     *
     * @throws InvalidAppMeta
     */
    #[TestWith(['nosuch', 'does not exist'])]
    #[TestWith(['', 'must not be empty'])]
    #[Test]
    public function rejectsUnresolvableAppDir(string $name, string $message): void
    {
        $appDir = $name === '' ? '' : "{$this->baseDir}/{$name}";

        $this->expectException(InvalidAppMeta::class);
        $this->expectExceptionMessage($message);

        AppMeta::fromAppDir($appDir, 'prod');
    }
}

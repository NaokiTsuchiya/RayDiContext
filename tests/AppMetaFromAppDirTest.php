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
 * The factory requires an absolute appDir, rejecting a relative one. Unlike before, it no
 * longer checks existence — that check now lives in the CLI (bin/ray-di-compile), since it
 * is argument validation rather than a type invariant.
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
     * bin/ray-di-compile passes the result in. An override reaches AppMeta verbatim, just
     * like appDir — compile-time and runtime have to agree on the literal, and resolving
     * it here would silently change it under a symlink.
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
     * A relative appDir is rejected, not resolved
     *
     * Left relative, it would reach BakedPathGuard as a needle that matches nearly every
     * literal — "." matches all of them. Resolving it (as a prior implementation did with
     * realpath()) would also absorb symlinks, which breaks BakedPathGuard's verbatim
     * comparison for a symlinked appDir (e.g. Capistrano's /app -> /releases/current): the
     * needle would no longer share a spelling with the literal actually baked into the
     * compiled scripts. Rejecting outright avoids both problems.
     *
     * @throws InvalidAppMeta
     */
    #[Test]
    public function rejectsRelativeAppDir(): void
    {
        $cwd = getcwd();
        static::assertNotFalse($cwd);
        chdir($this->appDir);

        try {
            $this->expectException(InvalidAppMeta::class);
            $this->expectExceptionMessage('must be an absolute path: "."');

            AppMeta::fromAppDir('.', 'prod');
        } finally {
            chdir($cwd);
        }
    }

    /**
     * An empty appDir is rejected
     *
     * A missing-but-absolute appDir is no longer rejected here: existence is argument
     * validation, not a type invariant, so that check now lives in the CLI
     * (bin/ray-di-compile's is_dir() check, covered by
     * BinCompileTest::failsWithStatusTwoOnMissingAppDir).
     *
     * @throws InvalidAppMeta
     */
    #[Test]
    public function rejectsEmptyAppDir(): void
    {
        $this->expectException(InvalidAppMeta::class);
        $this->expectExceptionMessage('must not be empty');

        AppMeta::fromAppDir('', 'prod');
    }
}

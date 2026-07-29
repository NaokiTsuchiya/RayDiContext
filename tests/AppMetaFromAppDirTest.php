<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\BakedPathFound;
use NaokiTsuchiya\RayDiContext\Exception\ExceptionInterface;
use NaokiTsuchiya\RayDiContext\Exception\InvalidAppMeta;
use NaokiTsuchiya\RayDiContext\Fake\FakeProdContext;
use NaokiTsuchiya\RayDiContext\Fake\Fs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function mkdir;
use function symlink;
use function uniqid;

/**
 * Covers the fromAppDir() factory; AppMetaTest covers the public constructor
 *
 * The factory no longer touches the filesystem: it validates appDir's shape (absolute or
 * not) but not its existence, so an app dir need not exist on disk for most cases here.
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
     * A namespaced class-string context (e.g. "App\ProdContext") is accepted verbatim:
     * unlike "/" and ".", "\" carries none of the OS-resolution risk that excludes those
     * two from CONTEXT_PATTERN, so a caller can pass a ::class-shaped context straight
     * through without folding it into a different alphabet first.
     *
     * @throws InvalidAppMeta
     */
    #[Test]
    public function defaults(): void
    {
        $context = FakeProdContext::class;
        $meta = AppMeta::fromAppDir($this->appDir, $context);

        static::assertSame($this->appDir, $meta->appDir);
        static::assertSame($context, $meta->context);
        static::assertSame("{$this->appDir}/var/di/{$context}", $meta->compileDir);
        static::assertSame("{$this->appDir}/var/tmp/{$context}", $meta->tmpDir);
    }

    /**
     * A context outside CONTEXT_PATTERN's alphabet (letters, digits, "_", "-", "\") is
     * rejected outright, as a whitelist rather than as a list of specific dangerous
     * spellings: "/" and "." (whether alone, doubled, or leading/trailing) would
     * otherwise collapse the interpolated compileDir/tmpDir back to "{appDir}/var/di"
     * itself — the parent shared by every context, so Cleaner emptying it would delete
     * every other context's compiled scripts too. A "/"-nested class-string
     * ("App/ProdContext") is rejected the same way — only "\" is accepted as a
     * namespace separator, not "/".
     *
     * @throws InvalidAppMeta
     */
    #[TestWith([''])]
    #[TestWith(['../prod'])]
    #[TestWith(['pro..d'])]
    #[TestWith(['prod/../../etc'])]
    #[TestWith(['.'])]
    #[TestWith(['/'])]
    #[TestWith(['./'])]
    #[TestWith(['/prod'])]
    #[TestWith(['prod/'])]
    #[TestWith(['prod//staging'])]
    #[TestWith(['prod/./staging'])]
    #[TestWith(['App/ProdContext'])]
    #[TestWith(['prod:staging'])]
    #[TestWith(['prod staging'])]
    #[Test]
    public function rejectsUnsafeContext(string $context): void
    {
        $this->expectException(InvalidAppMeta::class);

        AppMeta::fromAppDir($this->appDir, $context);
    }

    /**
     * Explicit compileDir/tmpDir override the conventional defaults independently
     *
     * fromAppDir() no longer reads the environment itself; a caller such as
     * bin/ray-di-compile passes the result in. appDir is only shape-checked, never
     * resolved, so an override still reaches AppMeta verbatim — compile-time and runtime
     * have to agree on the literal, and resolving it here would silently change it under
     * a symlink.
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
     * A relative or empty appDir is rejected outright rather than resolved
     *
     * A relative appDir is never resolved against the working directory: left as-is it
     * would reach BakedPathGuard as a needle that matches nearly every literal — "."
     * matches all of them — and fail the compile with a message that reads as a baked
     * path rather than as a bad argument. Unlike the earlier realpath()-based check,
     * this one never touches the filesystem, so no cwd juggling is needed to exercise it.
     *
     * @throws InvalidAppMeta
     */
    #[TestWith(['.', 'must be an absolute path'])]
    #[TestWith(['app', 'must be an absolute path'])]
    #[TestWith(['./app', 'must be an absolute path'])]
    #[TestWith(['', 'must not be empty'])]
    #[Test]
    public function rejectsInvalidAppDirShape(string $appDir, string $message): void
    {
        $this->expectException(InvalidAppMeta::class);
        $this->expectExceptionMessage($message);

        AppMeta::fromAppDir($appDir, 'prod');
    }

    /**
     * appDir need not exist on disk — existence is a caller concern, not this factory's
     *
     * @throws InvalidAppMeta
     */
    #[Test]
    public function doesNotRequireAppDirToExist(): void
    {
        $appDir = "{$this->baseDir}/nosuch";

        $meta = AppMeta::fromAppDir($appDir, 'prod');

        static::assertSame($appDir, $meta->appDir);
    }

    /**
     * An appDir reached through a symlink keeps the caller's spelling instead of
     * resolving to the symlink's target
     *
     * This is the guarantee BakedPathGuard depends on: compile time and runtime must
     * bind the exact same string for the guard's verbatim comparison to catch a leaked
     * path. Resolving the symlink here would make the guard fail open under a
     * Capistrano-style "current -> release" deployment layout.
     *
     * @throws BakedPathFound
     * @throws ExceptionInterface
     * @throws InvalidAppMeta
     */
    #[Test]
    public function preservesSymlinkSpellingAgainstBakedPathGuard(): void
    {
        $link = "{$this->baseDir}/current";
        symlink($this->appDir, $link);

        $meta = AppMeta::fromAppDir($link, 'prod');
        static::assertSame($link, $meta->appDir);

        mkdir($meta->compileDir, permissions: 0o755, recursive: true);
        file_put_contents("{$meta->compileDir}/baked.php", "<?php return '{$link}/src/Index.php';");

        $this->expectException(BakedPathFound::class);

        (new BakedPathGuard())($meta->compileDir, $meta);
    }
}

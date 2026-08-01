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
 * The factory validates appDir's shape but never touches the filesystem, so an app dir need
 * not exist on disk for most cases here.
 */
#[CoversClass(AppMeta::class)]
final class AppMetaFromAppDirTest extends TestCase
{
    /** @var non-empty-string Per-test working directory */
    private string $baseDir;

    /** @var non-empty-string Existing app dir to resolve */
    private string $appDir;

    /** {@inheritDoc} */
    protected function setUp(): void
    {
        $this->baseDir = __DIR__ . '/tmp/' . uniqid('meta_', more_entropy: true);
        $this->appDir = "{$this->baseDir}/app";
        mkdir($this->appDir, permissions: 0o755, recursive: true);
    }

    /** {@inheritDoc} */
    protected function tearDown(): void
    {
        Fs::removeDir($this->baseDir);
    }

    /**
     * Falls back to conventional paths under the app dir
     *
     * @throws ExceptionInterface
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

    /** @throws ExceptionInterface */
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
     * An override reaches AppMeta verbatim: compile time and runtime have to agree on the
     * literal, and resolving it here would silently change it under a symlink.
     *
     * @throws ExceptionInterface
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
     * @throws ExceptionInterface
     */
    #[Test]
    public function partialOverride(): void
    {
        $meta = AppMeta::fromAppDir($this->appDir, 'prod', compileDir: '/opt/di');

        static::assertSame('/opt/di', $meta->compileDir);
        static::assertSame("{$this->appDir}/var/tmp/prod", $meta->tmpDir);
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function trimsTrailingSlashes(): void
    {
        $meta = AppMeta::fromAppDir("{$this->appDir}/", 'prod', tmpDir: '/tmp/rw/');

        static::assertSame($this->appDir, $meta->appDir);
        static::assertSame("{$this->appDir}/var/di/prod", $meta->compileDir);
        static::assertSame('/tmp/rw', $meta->tmpDir);
    }

    /**
     * A relative appDir is rejected rather than resolved: left as-is it would reach
     * BakedPathGuard as a needle matching nearly every literal — "." matches all of them —
     * and fail the compile as a baked path rather than as a bad argument.
     *
     * @throws ExceptionInterface
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
     * @throws ExceptionInterface
     */
    #[Test]
    public function doesNotRequireAppDirToExist(): void
    {
        $appDir = "{$this->baseDir}/nosuch";

        $meta = AppMeta::fromAppDir($appDir, 'prod');

        static::assertSame($appDir, $meta->appDir);
    }

    /**
     * BakedPathGuard compares verbatim, so compile time and runtime must bind the same
     * string. Resolving the symlink would make the guard fail open under a
     * Capistrano-style "current -> release" layout.
     *
     * @throws ExceptionInterface
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

        (new BakedPathGuard())($meta);
    }
}

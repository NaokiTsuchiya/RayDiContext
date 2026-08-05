<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\ExceptionInterface;
use NaokiTsuchiya\RayDiContext\Exception\InvalidAppMeta;
use NaokiTsuchiya\RayDiContext\Fake\FakeProdContext;
use NaokiTsuchiya\RayDiContext\Support\Fs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

use function mkdir;
use function sprintf;
use function uniqid;

/** The public constructor and the fromAppDir() factory */
#[CoversClass(AppMeta::class)]
final class AppMetaTest extends TestCase
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

    /** @throws ExceptionInterface */
    #[Test]
    public function constructStoresGivenFields(): void
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
    #[Test]
    public function rejectsNonAbsoluteCompileDir(): void
    {
        $this->expectException(InvalidAppMeta::class);
        $this->expectExceptionMessage('AppMeta::$compileDir must be an absolute path: "var/di/prod"');

        new AppMeta('/app', 'prod', 'var/di/prod', '/app/var/tmp/prod');
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function rejectsNonAbsoluteTmpDir(): void
    {
        $this->expectException(InvalidAppMeta::class);
        $this->expectExceptionMessage('AppMeta::$tmpDir must be an absolute path: "var/tmp/prod"');

        new AppMeta('/app', 'prod', '/app/var/di/prod', 'var/tmp/prod');
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

    /** @throws ExceptionInterface */
    #[Test]
    public function appliesConventionalDefaults(): void
    {
        $context = FakeProdContext::class;
        $meta = AppMeta::fromAppDir($this->appDir, $context);

        static::assertSame($this->appDir, $meta->appDir);
        static::assertSame($context, $meta->context);
        static::assertSame("{$this->appDir}/var/di/{$context}", $meta->compileDir);
        static::assertSame("{$this->appDir}/var/tmp/{$context}", $meta->tmpDir);
    }

    /** @throws ExceptionInterface */
    #[TestWith([''], 'empty')]
    #[TestWith(['../prod'], 'parent directory traversal')]
    #[TestWith(['pro..d'], 'dots inside a word')]
    #[TestWith(['prod/../../etc'], 'traversal below a segment')]
    #[TestWith(['.'], 'current directory')]
    #[TestWith(['/'], 'separator alone')]
    #[TestWith(['./'], 'current directory with a trailing separator')]
    #[TestWith(['/prod'], 'leading separator')]
    #[TestWith(['prod/'], 'trailing separator')]
    #[TestWith(['prod//staging'], 'doubled separator')]
    #[TestWith(['prod/./staging'], 'current directory as a segment')]
    #[TestWith(['App/ProdContext'], 'namespace spelled with a separator')]
    #[TestWith(['prod:staging'], 'colon')]
    #[TestWith(['prod staging'], 'space')]
    #[Test]
    public function rejectsUnsafeContext(string $context): void
    {
        $this->expectException(InvalidAppMeta::class);
        $this->expectExceptionMessage(sprintf(
            'AppMeta::fromAppDir(): $context must contain only letters, digits, "_", "-", or "\\": "%s"',
            $context,
        ));

        AppMeta::fromAppDir($this->appDir, $context);
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function overridesCompileDirAndTmpDir(): void
    {
        $meta = AppMeta::fromAppDir($this->appDir, 'prod', '/opt/di', '/tmp/rw');

        static::assertSame('/opt/di', $meta->compileDir);
        static::assertSame('/tmp/rw', $meta->tmpDir);
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function overridesCompileDirOnly(): void
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

    /** @throws ExceptionInterface */
    #[TestWith(['app', 'must be an absolute path'], 'bare relative segment')]
    #[TestWith(['', 'must not be empty'], 'empty')]
    #[Test]
    public function rejectsInvalidAppDirShape(string $appDir, string $message): void
    {
        $this->expectException(InvalidAppMeta::class);
        $this->expectExceptionMessage($message);

        AppMeta::fromAppDir($appDir, 'prod');
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function rejectsNonAbsoluteCompileDirOverride(): void
    {
        $this->expectException(InvalidAppMeta::class);
        $this->expectExceptionMessage('AppMeta::$compileDir must be an absolute path: "var/di/prod"');

        AppMeta::fromAppDir($this->appDir, 'prod', 'var/di/prod');
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function doesNotRequireAppDirToExist(): void
    {
        $appDir = "{$this->baseDir}/nosuch";

        $meta = AppMeta::fromAppDir($appDir, 'prod');

        static::assertSame($appDir, $meta->appDir);
    }
}

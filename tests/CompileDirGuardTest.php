<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\ExceptionInterface;
use NaokiTsuchiya\RayDiContext\Exception\UnsafeCompileDir;
use NaokiTsuchiya\RayDiContext\Fake\Fs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function mkdir;
use function symlink;
use function uniqid;

/** The guard never removes anything, so the rejected paths are safe to exercise here */
#[CoversClass(CompileDirGuard::class)]
final class CompileDirGuardTest extends TestCase
{
    /** @var non-empty-string Per-test working directory */
    private string $baseDir;

    /** @var non-empty-string App dir the compile dir is compared against */
    private string $appDir;

    /** System under test */
    private CompileDirGuard $guard;

    /** {@inheritDoc} */
    protected function setUp(): void
    {
        $this->baseDir = __DIR__ . '/tmp/' . uniqid('guard_', more_entropy: true);
        $this->appDir = "{$this->baseDir}/app";
        mkdir($this->appDir, permissions: 0o755, recursive: true);
        $this->guard = new CompileDirGuard();
    }

    /** {@inheritDoc} */
    protected function tearDown(): void
    {
        Fs::removeDir($this->baseDir);
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function rejectsFilesystemRoot(): void
    {
        $this->expectException(UnsafeCompileDir::class);
        $this->expectExceptionMessage('Refusing to empty the filesystem root');

        ($this->guard)(new AppMeta('/app', 'prod', '/', '/tmp'));
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function rejectsRootReachedThroughDotSegment(): void
    {
        $this->expectException(UnsafeCompileDir::class);

        ($this->guard)(new AppMeta('/app', 'prod', '/.', '/tmp'));
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function rejectsCompileDirEqualToAppDir(): void
    {
        $this->expectException(UnsafeCompileDir::class);

        ($this->guard)(new AppMeta($this->appDir, 'prod', $this->appDir, "{$this->appDir}/var/tmp"));
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function rejectsCompileDirHoldingAppDir(): void
    {
        $this->expectException(UnsafeCompileDir::class);

        ($this->guard)(new AppMeta($this->appDir, 'prod', $this->baseDir, "{$this->appDir}/var/tmp"));
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function rejectsSymlinkedCompileDirHoldingAppDir(): void
    {
        $appDir = "{$this->baseDir}/real/app";
        mkdir($appDir, permissions: 0o755, recursive: true);
        $link = "{$this->baseDir}/link";
        symlink("{$this->baseDir}/real", $link);

        $this->expectException(UnsafeCompileDir::class);

        ($this->guard)(new AppMeta($appDir, 'prod', $link, "{$appDir}/var/tmp"));
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function rejectionImplementsPackageExceptionInterface(): void
    {
        try {
            ($this->guard)(new AppMeta('/app', 'prod', '/', '/tmp'));
            static::fail('UnsafeCompileDir was not thrown');
        } catch (UnsafeCompileDir $e) {
            static::assertInstanceOf(ExceptionInterface::class, $e);
        }
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function allowsCompileDirUnderAppDir(): void
    {
        $this->expectNotToPerformAssertions();

        ($this->guard)(AppMeta::fromAppDir($this->appDir, 'prod'));
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function allowsCompileDirSharingNamePrefixWithAppDir(): void
    {
        $this->expectNotToPerformAssertions();

        ($this->guard)(new AppMeta('/appdata', 'prod', '/app', '/tmp'));
    }
}

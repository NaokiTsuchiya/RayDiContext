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

/**
 * The guard never removes anything, so the rejected paths are safe to exercise here
 */
#[CoversClass(CompileDirGuard::class)]
final class CompileDirGuardTest extends TestCase
{
    /** @var non-empty-string Per-test working directory */
    private string $baseDir;

    /** @var non-empty-string App dir the compile dir is compared against */
    private string $appDir;

    /** System under test */
    private CompileDirGuard $guard;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->baseDir = __DIR__ . '/tmp/' . uniqid('guard_', more_entropy: true);
        $this->appDir = "{$this->baseDir}/app";
        mkdir($this->appDir, permissions: 0o755, recursive: true);
        $this->guard = new CompileDirGuard();
    }

    /**
     * {@inheritDoc}
     */
    protected function tearDown(): void
    {
        Fs::removeDir($this->baseDir);
    }

    /**
     * The filesystem root is rejected
     *
     * @throws UnsafeCompileDir
     */
    #[Test]
    public function rejectsFilesystemRoot(): void
    {
        $this->expectException(UnsafeCompileDir::class);
        $this->expectExceptionMessage('Refusing to empty the filesystem root');

        ($this->guard)(new AppMeta('fake', '/app', '/', '/tmp'));
    }

    /**
     * A path resolving to the root is rejected, not only the literal slash
     *
     * APP_COMPILE_DIR=/ falls back to the default because AppMeta trims trailing
     * slashes, but APP_COMPILE_DIR=/. reaches the compile dir verbatim.
     *
     * @throws UnsafeCompileDir
     */
    #[Test]
    public function rejectsRootReachedThroughDotSegment(): void
    {
        $this->expectException(UnsafeCompileDir::class);

        ($this->guard)(new AppMeta('fake', '/app', '/.', '/tmp'));
    }

    /**
     * A compile dir equal to the app dir is rejected
     *
     * @throws UnsafeCompileDir
     */
    #[Test]
    public function rejectsCompileDirEqualToAppDir(): void
    {
        $this->expectException(UnsafeCompileDir::class);

        ($this->guard)(new AppMeta('fake', $this->appDir, $this->appDir, "{$this->appDir}/var/tmp"));
    }

    /**
     * A compile dir holding the app dir is rejected
     *
     * This is the APP_COMPILE_DIR=/app typo when the app lives in /app/src.
     *
     * @throws UnsafeCompileDir
     */
    #[Test]
    public function rejectsCompileDirHoldingAppDir(): void
    {
        $this->expectException(UnsafeCompileDir::class);

        ($this->guard)(new AppMeta('fake', $this->appDir, $this->baseDir, "{$this->appDir}/var/tmp"));
    }

    /**
     * A symlinked compile dir holding the app dir is rejected
     *
     * A literal comparison would let the link through: the link and the app dir share
     * no prefix until both are resolved.
     *
     * @throws UnsafeCompileDir
     */
    #[Test]
    public function rejectsSymlinkedCompileDirHoldingAppDir(): void
    {
        $appDir = "{$this->baseDir}/real/app";
        mkdir($appDir, permissions: 0o755, recursive: true);
        $link = "{$this->baseDir}/link";
        symlink("{$this->baseDir}/real", $link);

        $this->expectException(UnsafeCompileDir::class);

        ($this->guard)(new AppMeta('fake', $appDir, $link, "{$appDir}/var/tmp"));
    }

    /**
     * The rejection is catchable as a package exception
     *
     * @throws UnsafeCompileDir
     */
    #[Test]
    public function rejectionImplementsPackageExceptionInterface(): void
    {
        try {
            ($this->guard)(new AppMeta('fake', '/app', '/', '/tmp'));
            static::fail('UnsafeCompileDir was not thrown');
        } catch (UnsafeCompileDir $e) {
            static::assertInstanceOf(ExceptionInterface::class, $e);
        }
    }

    /**
     * The conventional compile dir under the app dir is allowed
     *
     * @throws UnsafeCompileDir
     */
    #[Test]
    public function allowsCompileDirUnderAppDir(): void
    {
        $this->expectNotToPerformAssertions();

        ($this->guard)(AppMeta::fromAppDir('fake', $this->appDir, 'prod'));
    }

    /**
     * A compile dir sharing a name prefix with the app dir is allowed
     *
     * /app is not an ancestor of /appdata, so matching on the prefix alone would
     * reject a legitimate compile dir.
     *
     * @throws UnsafeCompileDir
     */
    #[Test]
    public function allowsCompileDirSharingNamePrefixWithAppDir(): void
    {
        $this->expectNotToPerformAssertions();

        ($this->guard)(new AppMeta('fake', '/appdata', '/app', '/tmp'));
    }
}

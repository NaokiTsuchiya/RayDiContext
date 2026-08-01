<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\BakedPathFound;
use NaokiTsuchiya\RayDiContext\Exception\ExceptionInterface;
use NaokiTsuchiya\RayDiContext\Support\Fs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function mkdir;
use function sprintf;
use function uniqid;

#[CoversClass(BakedPathGuard::class)]
#[CoversClass(BakedPathScanner::class)]
final class BakedPathGuardBoundaryTest extends TestCase
{
    /** Per-test working directory */
    private string $baseDir;

    /** Meta whose tmp dir lives outside the app dir */
    private AppMeta $meta;

    /** System under test */
    private BakedPathGuard $guard;

    /** @throws ExceptionInterface */
    protected function setUp(): void
    {
        $this->baseDir = __DIR__ . '/tmp/' . uniqid('guard_boundary_', more_entropy: true);
        $appDir = "{$this->baseDir}/app";
        $this->meta = new AppMeta($appDir, 'prod', "{$appDir}/var/di/prod", "{$this->baseDir}/rw-tmp");
        mkdir($this->meta->compileDir, permissions: 0o755, recursive: true);
        $this->guard = new BakedPathGuard();
    }

    /** {@inheritDoc} */
    protected function tearDown(): void
    {
        Fs::removeDir($this->baseDir);
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function allowsPathInsideCompileDir(): void
    {
        file_put_contents(
            "{$this->meta->compileDir}/script-path.php",
            data: "<?php return '{$this->meta->compileDir}/scripts/x.php';",
        );

        $this->expectNotToPerformAssertions();

        ($this->guard)($this->meta);
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function detectsTmpDirNestedUnderCompileDir(): void
    {
        $meta = new AppMeta(
            $this->meta->appDir,
            $this->meta->context,
            $this->meta->compileDir,
            "{$this->meta->compileDir}/tmp",
        );
        $baked = "{$this->meta->compileDir}/baked.php";
        file_put_contents($baked, data: "<?php return '{$meta->tmpDir}/cache';");

        $this->expectException(BakedPathFound::class);
        $this->expectExceptionMessage(sprintf('Baked path "%s" found in %s.', $meta->tmpDir, $baked));

        ($this->guard)($meta);
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function detectsPathWithCompileDirStringPrefix(): void
    {
        $baked = "{$this->meta->compileDir}/baked.php";
        file_put_contents($baked, data: "<?php return '{$this->meta->compileDir}uction_logs/app.log';");

        $this->expectException(BakedPathFound::class);
        $this->expectExceptionMessage(sprintf('Baked path "%s" found in %s.', $this->meta->appDir, $baked));

        ($this->guard)($this->meta);
    }
}

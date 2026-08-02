<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\BakedPathFound;
use NaokiTsuchiya\RayDiContext\Exception\ExceptionInterface;
use NaokiTsuchiya\RayDiContext\Support\SeparatedDirFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function mkdir;
use function sprintf;
use function symlink;

#[CoversClass(BakedPathGuard::class)]
final class BakedPathGuardBoundaryTest extends TestCase
{
    /** Working directory and meta shared by the guard test classes */
    private SeparatedDirFixture $fixture;

    /** Meta whose tmp dir lives outside the app dir */
    private AppMeta $meta;

    /** System under test */
    private BakedPathGuard $guard;

    /** @throws ExceptionInterface */
    protected function setUp(): void
    {
        $this->fixture = new SeparatedDirFixture('guard_boundary_');
        $this->meta = $this->fixture->meta;
        $this->guard = new BakedPathGuard();
    }

    /** {@inheritDoc} */
    protected function tearDown(): void
    {
        $this->fixture->remove();
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

    /** @throws ExceptionInterface */
    #[Test]
    public function preservesSymlinkSpellingAgainstBakedPathGuard(): void
    {
        $target = "{$this->fixture->baseDir}/link-target";
        mkdir($target, permissions: 0o755, recursive: true);
        $link = "{$this->fixture->baseDir}/current";
        symlink($target, $link);

        $meta = AppMeta::fromAppDir($link, 'prod');
        static::assertSame($link, $meta->appDir);

        mkdir($meta->compileDir, permissions: 0o755, recursive: true);
        file_put_contents("{$meta->compileDir}/baked.php", "<?php return '{$link}/src/Index.php';");

        $this->expectException(BakedPathFound::class);

        (new BakedPathGuard())($meta);
    }
}

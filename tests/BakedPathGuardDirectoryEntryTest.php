<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\BakedPathFound;
use NaokiTsuchiya\RayDiContext\Exception\ExceptionInterface;
use NaokiTsuchiya\RayDiContext\Exception\ScriptNotReadable;
use NaokiTsuchiya\RayDiContext\Support\SeparatedDirFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function mkdir;
use function sprintf;
use function symlink;

/**
 * A compileDir entry whose name ends in ".php" can be a directory, or a symlink to one, not a script
 */
#[CoversClass(BakedPathGuard::class)]
final class BakedPathGuardDirectoryEntryTest extends TestCase
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
        $this->fixture = new SeparatedDirFixture('guard_dir_');
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
    public function throwsOnSymlinkToDirectoryNamedLikeAScript(): void
    {
        $targetDir = "{$this->fixture->baseDir}/link-target";
        mkdir($targetDir, permissions: 0o755, recursive: true);
        $link = "{$this->meta->compileDir}/cache.php";
        symlink($targetDir, $link);

        $this->expectException(ScriptNotReadable::class);
        $this->expectExceptionMessage($link);

        ($this->guard)($this->meta);
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function scansScriptsInsideADirectoryNamedLikeAScript(): void
    {
        $nestedDir = "{$this->meta->compileDir}/cache.php";
        mkdir($nestedDir, permissions: 0o755, recursive: true);
        $nestedScript = "{$nestedDir}/nested.php";
        file_put_contents($nestedScript, "<?php return '{$this->meta->appDir}/src/Index.php';");

        $this->expectException(BakedPathFound::class);
        $this->expectExceptionMessage(sprintf('Baked path "%s" found in %s.', $this->meta->appDir, $nestedScript));

        ($this->guard)($this->meta);
    }
}

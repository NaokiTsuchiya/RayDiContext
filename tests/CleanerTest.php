<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use FilesystemIterator;
use NaokiTsuchiya\RayDiContext\Exception\CompileDirNotWritable;
use NaokiTsuchiya\RayDiContext\Exception\ExceptionInterface;
use NaokiTsuchiya\RayDiContext\Exception\RemoveFailed;
use NaokiTsuchiya\RayDiContext\Exception\UnsafeCompileDir;
use NaokiTsuchiya\RayDiContext\Fake\FakeRejectingGuard;
use NaokiTsuchiya\RayDiContext\Support\Fs;
use NaokiTsuchiya\RayDiContext\Support\PermissionBits;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

use function chmod;
use function copy;
use function file_put_contents;
use function is_link;
use function iterator_count;
use function mkdir;
use function symlink;
use function uniqid;

#[CoversClass(Cleaner::class)]
final class CleanerTest extends TestCase
{
    /** @var non-empty-string Per-test working directory */
    private string $baseDir;

    /** {@inheritDoc} */
    protected function setUp(): void
    {
        $this->baseDir = __DIR__ . '/tmp/' . uniqid('cleaner_', more_entropy: true);
    }

    /** {@inheritDoc} */
    protected function tearDown(): void
    {
        Fs::removeDir($this->baseDir);
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function createsMissingCompileDir(): void
    {
        $compileDir = "{$this->baseDir}/var/di/prod";

        (new Cleaner())($this->meta($compileDir));

        static::assertDirectoryExists($compileDir);
        static::assertSame(0, iterator_count(new FilesystemIterator($compileDir)));
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function recreatesCompileDirAsEmpty(): void
    {
        $compileDir = "{$this->baseDir}/di";
        mkdir("{$compileDir}/nested", permissions: 0o755, recursive: true);
        file_put_contents("{$compileDir}/stale.php", data: '<?php return 0;');
        file_put_contents("{$compileDir}/nested/stale.php", data: '<?php return 0;');

        (new Cleaner())($this->meta($compileDir));

        static::assertDirectoryExists($compileDir);
        static::assertSame(0, iterator_count(new FilesystemIterator($compileDir)));
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function remainsInvokableRepeatedly(): void
    {
        $compileDir = "{$this->baseDir}/di";
        $cleaner = new Cleaner();

        $meta = $this->meta($compileDir);
        $cleaner($meta);
        file_put_contents("{$compileDir}/a.php", data: '<?php return 0;');
        $cleaner($meta);

        static::assertDirectoryExists($compileDir);
        static::assertSame(0, iterator_count(new FilesystemIterator($compileDir)));
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function removesSymlinkWithoutFollowingIt(): void
    {
        $compileDir = "{$this->baseDir}/di";
        $target = "{$this->baseDir}/target";
        mkdir($compileDir, permissions: 0o755, recursive: true);
        mkdir($target, permissions: 0o755, recursive: true);
        file_put_contents("{$target}/keep.php", data: '<?php return 0;');
        symlink($target, "{$compileDir}/link");

        (new Cleaner())($this->meta($compileDir));

        static::assertSame(0, iterator_count(new FilesystemIterator($compileDir)));
        static::assertFileExists("{$target}/keep.php");
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function emptiesSymlinkedCompileDirInPlace(): void
    {
        $target = "{$this->baseDir}/real-di";
        mkdir($target, permissions: 0o755, recursive: true);
        file_put_contents("{$target}/stale.php", data: '<?php return 0;');
        $link = "{$this->baseDir}/di-link";
        symlink($target, $link);

        (new Cleaner())($this->meta($link));

        static::assertTrue(is_link($link));
        static::assertDirectoryExists($target);
        static::assertSame(0, iterator_count(new FilesystemIterator($link)));
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function throwsWhenCompileDirCannotBeCreated(): void
    {
        $blocker = "{$this->baseDir}/blocker";
        mkdir($this->baseDir, permissions: 0o755, recursive: true);
        file_put_contents($blocker, data: '');
        $compileDir = "{$blocker}/di";

        $this->expectException(CompileDirNotWritable::class);
        $this->expectExceptionMessage($compileDir);

        (new Cleaner())($this->meta($compileDir));
    }

    /** @throws ExceptionInterface */
    #[TestWith([0o005], 'cannot list')]
    #[TestWith([0o405], 'cannot traverse')]
    #[Test]
    public function rejectsACompileDirItCannotRead(int $mode): void
    {
        PermissionBits::skipUnlessEnforced($this->baseDir);

        $compileDir = "{$this->baseDir}/di";
        mkdir($compileDir, permissions: 0o700, recursive: true);
        copy(Fs::SCRIPT, "{$compileDir}/stale.php");
        chmod($compileDir, permissions: $mode);

        try {
            (new Cleaner())($this->meta($compileDir));
            static::fail('RemoveFailed was not thrown');
        } catch (RemoveFailed $e) {
            static::assertStringContainsString($compileDir, $e->getMessage());
        } finally {
            chmod($compileDir, permissions: 0o700);
        }

        static::assertFileExists("{$compileDir}/stale.php");
    }

    /** @throws ExceptionInterface */
    #[TestWith([0o005], 'cannot list')]
    #[TestWith([0o405], 'cannot traverse')]
    #[Test]
    public function rejectsANestedDirectoryItCannotRead(int $mode): void
    {
        PermissionBits::skipUnlessEnforced($this->baseDir);

        $compileDir = "{$this->baseDir}/di";
        $nested = "{$compileDir}/nested";
        mkdir($nested, permissions: 0o700, recursive: true);
        copy(Fs::SCRIPT, "{$nested}/stale.php");
        chmod($nested, permissions: $mode);

        try {
            (new Cleaner())($this->meta($compileDir));
            static::fail('RemoveFailed was not thrown');
        } catch (RemoveFailed $e) {
            static::assertStringContainsString($nested, $e->getMessage());
        } finally {
            chmod($nested, permissions: 0o700);
        }

        static::assertFileExists("{$nested}/stale.php");
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function rejectsCompileDirHoldingAppDirWithoutRemovingAnything(): void
    {
        $appDir = "{$this->baseDir}/app";
        $compileDir = "{$appDir}/var/di/prod";
        mkdir($compileDir, permissions: 0o755, recursive: true);
        copy(Fs::SCRIPT, "{$appDir}/keep.php");
        copy(Fs::SCRIPT, "{$compileDir}/stale.php");
        $meta = new AppMeta($appDir, 'prod', $this->baseDir, "{$appDir}/var/tmp");

        try {
            (new Cleaner())($meta);
            static::fail('UnsafeCompileDir was not thrown');
        } catch (UnsafeCompileDir) {
            static::assertFileExists("{$appDir}/keep.php");
        }
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function honoursApplicationSuppliedGuard(): void
    {
        $appDir = "{$this->baseDir}/app";
        $compileDir = "{$this->baseDir}/di";
        mkdir($compileDir, permissions: 0o755, recursive: true);
        copy(Fs::SCRIPT, "{$compileDir}/stale.php");
        $meta = new AppMeta($appDir, 'prod', $compileDir, "{$this->baseDir}/tmp");

        try {
            (new Cleaner(new FakeRejectingGuard()))($meta);
            static::fail('UnsafeCompileDir was not thrown');
        } catch (UnsafeCompileDir $e) {
            static::assertStringContainsString('Rejected by the application guard:', $e->getMessage());
            static::assertFileExists("{$compileDir}/stale.php");
        }
    }

    /**
     * @param non-empty-string $compileDir
     * @throws ExceptionInterface
     */
    private function meta(string $compileDir): AppMeta
    {
        return new AppMeta("{$this->baseDir}/app", 'prod', $compileDir, "{$this->baseDir}/tmp");
    }
}

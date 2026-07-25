<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use FilesystemIterator;
use NaokiTsuchiya\RayDiContext\Exception\UnsafeCompileDir;
use NaokiTsuchiya\RayDiContext\Fake\Fs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function file_put_contents;
use function is_link;
use function iterator_count;
use function mkdir;
use function restore_error_handler;
use function set_error_handler;
use function symlink;
use function uniqid;

#[CoversClass(Cleaner::class)]
final class CleanerTest extends TestCase
{
    /** @var non-empty-string Per-test working directory */
    private string $baseDir;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->baseDir = __DIR__ . '/tmp/' . uniqid('cleaner_', more_entropy: true);
    }

    /**
     * {@inheritDoc}
     */
    protected function tearDown(): void
    {
        Fs::removeDir($this->baseDir);
    }

    /**
     * A missing compile dir is created recursively
     *
     * @throws RuntimeException
     */
    #[Test]
    public function createsMissingCompileDir(): void
    {
        $compileDir = "{$this->baseDir}/var/di/prod";

        (new Cleaner())($this->meta($compileDir));

        static::assertDirectoryExists($compileDir);
        static::assertSame(0, iterator_count(new FilesystemIterator($compileDir)));
    }

    /**
     * An existing compile dir is recreated empty, including nested contents
     *
     * @throws RuntimeException
     */
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

    /**
     * The cleaner is safely invokable repeatedly on its own
     *
     * @throws RuntimeException
     */
    #[Test]
    public function invokableRepeatedly(): void
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

    /**
     * A symlink inside the compile dir is removed without following it
     *
     * @throws RuntimeException
     */
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

    /**
     * A compile dir that is itself a symlink is emptied in place, keeping the link
     *
     * @throws RuntimeException
     */
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

    /**
     * A compile dir that cannot be created raises a RuntimeException naming the path
     *
     * A regular file blocking a path component is a portable way to make mkdir() fail
     * without relying on permissions, which root ignores.
     *
     * @throws RuntimeException
     */
    #[Test]
    public function throwsWhenCompileDirCannotBeCreated(): void
    {
        $blocker = "{$this->baseDir}/blocker";
        mkdir($this->baseDir, permissions: 0o755, recursive: true);
        file_put_contents($blocker, data: '');
        $compileDir = "{$blocker}/di";

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($compileDir);

        // mkdir() emits its own E_WARNING on top of the exception the cleaner throws;
        // a no-op handler swallows it since the exception is what's under test.
        set_error_handler(static fn(): bool => true);
        try {
            (new Cleaner())($this->meta($compileDir));
        } finally {
            restore_error_handler();
        }
    }

    /**
     * A compile dir holding the app dir is rejected before anything is removed
     *
     * @throws RuntimeException
     */
    #[Test]
    public function rejectsCompileDirHoldingAppDirWithoutRemovingAnything(): void
    {
        $appDir = "{$this->baseDir}/app";
        mkdir($appDir, permissions: 0o755, recursive: true);
        file_put_contents("{$appDir}/keep.php", data: '<?php return 0;');
        $meta = new AppMeta('fake', $appDir, $this->baseDir, "{$appDir}/var/tmp");

        try {
            (new Cleaner())($meta);
            static::fail('UnsafeCompileDir was not thrown');
        } catch (UnsafeCompileDir) {
            static::assertFileExists("{$appDir}/keep.php");
        }
    }

    /**
     * Returns a meta whose app dir is unrelated to the given compile dir
     *
     * @param non-empty-string $compileDir
     */
    private function meta(string $compileDir): AppMeta
    {
        return new AppMeta('fake', "{$this->baseDir}/app", $compileDir, "{$this->baseDir}/tmp");
    }
}

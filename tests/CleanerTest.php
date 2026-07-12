<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use FilesystemIterator;
use NaokiTsuchiya\RayDiContext\Fake\Fs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function file_put_contents;
use function is_link;
use function iterator_count;
use function mkdir;
use function symlink;
use function uniqid;

#[CoversClass(Cleaner::class)]
final class CleanerTest extends TestCase
{
    /** Per-test working directory */
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

        (new Cleaner())($compileDir);

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

        (new Cleaner())($compileDir);

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

        $cleaner($compileDir);
        file_put_contents("{$compileDir}/a.php", data: '<?php return 0;');
        $cleaner($compileDir);

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

        (new Cleaner())($compileDir);

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

        (new Cleaner())($link);

        static::assertTrue(is_link($link));
        static::assertDirectoryExists($target);
        static::assertSame(0, iterator_count(new FilesystemIterator($link)));
    }
}

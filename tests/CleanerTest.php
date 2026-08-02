<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use FilesystemIterator;
use NaokiTsuchiya\RayDiContext\Exception\ExceptionInterface;
use NaokiTsuchiya\RayDiContext\Support\Fs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function is_link;
use function iterator_count;
use function mkdir;
use function symlink;
use function uniqid;

/** Emptying a compile dir the cleaner can reach; CleanerRejectionTest covers the ones it cannot */
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

    /**
     * @param non-empty-string $compileDir
     * @throws ExceptionInterface
     */
    private function meta(string $compileDir): AppMeta
    {
        return new AppMeta("{$this->baseDir}/app", 'prod', $compileDir, "{$this->baseDir}/tmp");
    }
}

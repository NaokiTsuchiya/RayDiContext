<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use FilesystemIterator;
use NaokiTsuchiya\RayDiContext\Exception\BakedPathFound;
use NaokiTsuchiya\RayDiContext\Exception\ExceptionInterface;
use NaokiTsuchiya\RayDiContext\Exception\UnsafeCompileDir;
use NaokiTsuchiya\RayDiContext\Fake\FakeBakedContext;
use NaokiTsuchiya\RayDiContext\Fake\FakeCar;
use NaokiTsuchiya\RayDiContext\Fake\FakeCarInterface;
use NaokiTsuchiya\RayDiContext\Fake\FakeProdContext;
use NaokiTsuchiya\RayDiContext\Support\AppDirFixture;
use NaokiTsuchiya\RayDiContext\Support\Fs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ray\Compiler\CompiledInjector;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function chmod;
use function dirname;
use function file_put_contents;
use function fileperms;
use function glob;
use function hash_file;
use function is_dir;
use function ksort;
use function mkdir;

#[CoversClass(CompileRunner::class)]
final class CompileRunnerTest extends TestCase
{
    /** Working directory and meta shared by the compile test classes */
    private AppDirFixture $fixture;

    /** Meta with conventional paths under the app dir */
    private AppMeta $meta;

    /** System under test */
    private CompileRunner $runner;

    /** @throws ExceptionInterface */
    protected function setUp(): void
    {
        $this->fixture = new AppDirFixture('runner_');
        $this->meta = $this->fixture->meta;
        $this->runner = new CompileRunner(new MapContextProvider([
            'prod' => FakeProdContext::class,
            'baked' => FakeBakedContext::class,
        ]));
    }

    /** {@inheritDoc} */
    protected function tearDown(): void
    {
        $exists = is_dir($this->meta->compileDir);
        if ($exists) {
            chmod($this->meta->compileDir, permissions: 0o755);
        }

        $this->fixture->remove();
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function runCleansAndCompiles(): void
    {
        mkdir($this->meta->compileDir, permissions: 0o755, recursive: true);
        file_put_contents("{$this->meta->compileDir}/stale.php", data: '<?php return 0;');

        $this->runner->run($this->meta);

        static::assertFileDoesNotExist("{$this->meta->compileDir}/stale.php");
        static::assertNotSame([], glob("{$this->meta->compileDir}/*FakeCarInterface*.php"));
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function resolvesFromReadOnlyCompileDir(): void
    {
        $this->runner->run($this->meta);
        chmod($this->meta->compileDir, permissions: 0o555);
        $before = $this->snapshot($this->meta->compileDir);

        $injector = (new MapContextProvider([
            'prod' => FakeProdContext::class,
        ]))->get($this->meta)->getInjectorInstance();

        static::assertInstanceOf(CompiledInjector::class, $injector);
        static::assertInstanceOf(FakeCar::class, $injector->getInstance(FakeCarInterface::class));
        static::assertSame($before, $this->snapshot($this->meta->compileDir));
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function resolvesWithoutCompileTimeTmpDir(): void
    {
        $this->runner->run($this->meta);
        Fs::removeDir(dirname($this->meta->tmpDir));
        $runtimeMeta = new AppMeta(
            $this->meta->appDir,
            'prod',
            $this->meta->compileDir,
            "{$this->fixture->baseDir}/absent-tmp",
        );

        $injector = (new MapContextProvider(['prod' => FakeProdContext::class]))->get(
            $runtimeMeta,
        )->getInjectorInstance();

        static::assertInstanceOf(FakeCar::class, $injector->getInstance(FakeCarInterface::class));
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function runMakesCompiledScriptsWorldReadable(): void
    {
        $this->runner->run($this->meta);

        static::assertSame(0o755, $this->mode($this->meta->compileDir));
        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->meta->compileDir, FilesystemIterator::SKIP_DOTS),
        );
        $count = 0;
        /** @var SplFileInfo $entry */
        foreach ($entries as $entry) {
            $pathname = $entry->getPathname();
            $mode = $this->mode($pathname);
            $isDir = $entry->isDir();
            $required = $isDir ? 0o005 : 0o004;
            static::assertSame($required, $mode & $required, $pathname);
            $isScript = $entry->getExtension() === 'php';
            if ($isScript) {
                static::assertSame(0o644, $mode, $pathname);
            }

            $count++;
        }

        static::assertGreaterThan(0, $count);
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function runEmptiesTheCompileDirWhenTheGuardRejects(): void
    {
        $bakedMeta = new AppMeta($this->meta->appDir, 'baked', $this->meta->compileDir, $this->meta->tmpDir);

        try {
            $this->runner->run($bakedMeta);
            static::fail('BakedPathFound was not thrown');
        } catch (BakedPathFound $e) {
            static::assertStringContainsString($this->meta->appDir, $e->getMessage());
            static::assertSame([], glob("{$this->meta->compileDir}/*"));
        }
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function runRejectsUnsafeCompileDirBeforeCleaning(): void
    {
        $appDir = $this->meta->appDir;
        $unsafeMeta = new AppMeta($appDir, 'prod', $appDir, $this->meta->tmpDir);

        try {
            $this->runner->run($unsafeMeta);
            static::fail('UnsafeCompileDir was not thrown');
        } catch (UnsafeCompileDir) {
            static::assertDirectoryExists($this->meta->tmpDir);
        }
    }

    /**
     * @return array<string, string>
     * @throws ExceptionInterface
     */
    private function snapshot(string $dir): array
    {
        $files = [];
        /** @var SplFileInfo $entry */
        foreach (new FilesystemIterator($dir) as $entry) {
            $pathname = $entry->getPathname();
            $hash = hash_file('sha256', $pathname);
            $files[$pathname] = $hash === false ? '' : $hash;
        }

        ksort($files);

        return $files;
    }

    /** Returns the permission bits of a path */
    private function mode(string $path): int
    {
        return (int) fileperms($path) & 0o777;
    }
}

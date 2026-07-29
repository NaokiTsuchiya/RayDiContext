<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use FilesystemIterator;
use NaokiTsuchiya\RayDiContext\Exception\BakedPathFound;
use NaokiTsuchiya\RayDiContext\Exception\InvalidAppMeta;
use NaokiTsuchiya\RayDiContext\Exception\UnknownContext;
use NaokiTsuchiya\RayDiContext\Exception\UnsafeCompileDir;
use NaokiTsuchiya\RayDiContext\Fake\FakeBakedContext;
use NaokiTsuchiya\RayDiContext\Fake\FakeCar;
use NaokiTsuchiya\RayDiContext\Fake\FakeCarInterface;
use NaokiTsuchiya\RayDiContext\Fake\FakeProdContext;
use NaokiTsuchiya\RayDiContext\Fake\Fs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ray\Compiler\CompiledInjector;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionException;
use RuntimeException;
use SplFileInfo;

use function chmod;
use function file_put_contents;
use function filemtime;
use function fileperms;
use function filesize;
use function glob;
use function is_dir;
use function ksort;
use function mkdir;
use function uniqid;

#[CoversClass(CompileRunner::class)]
final class CompileRunnerTest extends TestCase
{
    /** Per-test working directory */
    private string $baseDir;

    /** Meta with conventional paths under the app dir */
    private AppMeta $meta;

    /** System under test */
    private CompileRunner $runner;

    /**
     * {@inheritDoc}
     *
     * @throws InvalidAppMeta
     */
    protected function setUp(): void
    {
        $this->baseDir = __DIR__ . '/tmp/' . uniqid('runner_', more_entropy: true);
        $appDir = "{$this->baseDir}/app";
        mkdir("{$appDir}/var/tmp/prod", permissions: 0o755, recursive: true);
        $this->meta = AppMeta::fromAppDir($appDir, 'prod');
        $this->runner = new CompileRunner(new MapContextProvider([
            'prod' => FakeProdContext::class,
            'baked' => FakeBakedContext::class,
        ]));
    }

    /**
     * {@inheritDoc}
     */
    protected function tearDown(): void
    {
        $exists = is_dir($this->meta->compileDir);
        if ($exists) {
            chmod($this->meta->compileDir, permissions: 0o755); // testResolvesFromReadOnlyCompileDir makes it read-only
        }

        Fs::removeDir($this->baseDir);
    }

    /**
     * run() cleans stale scripts and compiles the context module
     *
     * @throws BakedPathFound
     * @throws RuntimeException
     */
    #[Test]
    public function runCleansAndCompiles(): void
    {
        mkdir($this->meta->compileDir, permissions: 0o755, recursive: true);
        file_put_contents("{$this->meta->compileDir}/stale.php", data: '<?php return 0;');

        $this->runner->run($this->meta);

        static::assertFileDoesNotExist("{$this->meta->compileDir}/stale.php");
        static::assertNotSame([], glob("{$this->meta->compileDir}/*FakeCarInterface*.php"));
    }

    /**
     * The compiled context resolves instances from a read-only compile dir
     *
     * This is the readOnlyRootFilesystem scenario: the compile dir is baked into the
     * image and never written to at runtime.
     *
     * @throws BakedPathFound
     * @throws RuntimeException
     * @throws UnknownContext
     * @throws ReflectionException
     */
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
        // No file was created, changed, or removed in the compile dir at runtime
        static::assertSame($before, $this->snapshot($this->meta->compileDir));
    }

    /**
     * Runtime resolution does not depend on the compile-time tmp dir
     *
     * The tmp dir that existed when the image was built may be absent at runtime; the
     * compiled context must still resolve.
     *
     * @throws BakedPathFound
     * @throws RuntimeException
     * @throws UnknownContext
     * @throws ReflectionException
     */
    #[Test]
    public function resolvesWithoutCompileTimeTmpDir(): void
    {
        $this->runner->run($this->meta);
        Fs::removeDir("{$this->baseDir}/app/var/tmp");
        $runtimeMeta = new AppMeta(
            "{$this->baseDir}/app",
            'prod',
            $this->meta->compileDir,
            "{$this->baseDir}/absent-tmp",
        );

        $injector = (new MapContextProvider(['prod' => FakeProdContext::class]))->get(
            $runtimeMeta,
        )->getInjectorInstance();

        static::assertInstanceOf(FakeCar::class, $injector->getInstance(FakeCarInterface::class));
    }

    /**
     * Every compiled entry is readable by a user other than the one that compiled
     *
     * This is the build-as-root, run-as-non-root container: Ray.Compiler writes the
     * scripts 0600 through tempnam(), which leaves them unreadable to the runtime user
     * once the compile dir is baked into the image.
     *
     * @throws BakedPathFound
     * @throws RuntimeException
     */
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
            // A directory additionally has to be traversable to reach what is inside
            $isDir = $entry->isDir();
            $required = $isDir ? 0o005 : 0o004;
            static::assertSame($required, $mode & $required, $pathname);
            // Compiled scripts always arrive at 0600 from tempnam(), so they are always
            // rewritten: their mode is exact, not merely readable.
            $isScript = $entry->getExtension() === 'php';
            if ($isScript) {
                static::assertSame(0o644, $mode, $pathname);
            }

            $count++;
        }

        static::assertGreaterThan(0, $count);
    }

    /**
     * The guard runs after compilation and rejects baked runtime paths
     *
     * @throws RuntimeException
     */
    #[Test]
    public function runGuardsBakedPathAfterCompile(): void
    {
        $bakedMeta = new AppMeta($this->meta->appDir, 'baked', $this->meta->compileDir, $this->meta->tmpDir);

        try {
            $this->runner->run($bakedMeta);
            static::fail('BakedPathFound was not thrown');
        } catch (BakedPathFound $e) {
            static::assertStringContainsString($this->meta->appDir, $e->getMessage());
            // The compiled scripts exist: compilation preceded the guard
            static::assertNotSame([], glob("{$this->meta->compileDir}/*.php"));
        }
    }

    /**
     * A compile dir that holds the app dir is rejected before the clean step runs
     *
     * This is the APP_COMPILE_DIR typo the guard exists for: the run must abort with
     * the app still on disk.
     *
     * @throws BakedPathFound
     * @throws RuntimeException
     */
    #[Test]
    public function runRejectsUnsafeCompileDirBeforeCleaning(): void
    {
        $appDir = "{$this->baseDir}/app";
        $unsafeMeta = new AppMeta($appDir, 'prod', $appDir, $this->meta->tmpDir);

        try {
            $this->runner->run($unsafeMeta);
            static::fail('UnsafeCompileDir was not thrown');
        } catch (UnsafeCompileDir) {
            // The tmp dir set up under the app dir is still there: nothing was removed
            static::assertDirectoryExists($this->meta->tmpDir);
        }
    }

    /**
     * Returns file names with size and mtime for change detection
     *
     * @return array<string, list{int, int}>
     *
     * @throws RuntimeException
     */
    private function snapshot(string $dir): array
    {
        $files = [];
        /** @var SplFileInfo $entry */
        foreach (new FilesystemIterator($dir) as $entry) {
            $pathname = $entry->getPathname();
            $files[$pathname] = [(int) filesize($pathname), (int) filemtime($pathname)];
        }

        ksort($files);

        return $files;
    }

    /**
     * Returns the permission bits of a path
     */
    private function mode(string $path): int
    {
        return (int) fileperms($path) & 0o777;
    }
}

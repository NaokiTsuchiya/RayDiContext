<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use FilesystemIterator;
use NaokiTsuchiya\RayDiContext\Exception\BakedPathFound;
use NaokiTsuchiya\RayDiContext\Exception\UnknownEnv;
use NaokiTsuchiya\RayDiContext\Fake\FakeBakedContext;
use NaokiTsuchiya\RayDiContext\Fake\FakeCar;
use NaokiTsuchiya\RayDiContext\Fake\FakeCarInterface;
use NaokiTsuchiya\RayDiContext\Fake\FakeProdContext;
use NaokiTsuchiya\RayDiContext\Fake\Fs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ray\Compiler\CompiledInjector;
use RuntimeException;
use SplFileInfo;

use function chmod;
use function file_put_contents;
use function filemtime;
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
     */
    protected function setUp(): void
    {
        $this->baseDir = __DIR__ . '/tmp/' . uniqid('runner_', more_entropy: true);
        $appDir = "{$this->baseDir}/app";
        mkdir("{$appDir}/var/tmp/prod", permissions: 0o755, recursive: true);
        $this->meta = AppMeta::fromAppDir('fake', $appDir, 'prod');
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
     * run() cleans stale scripts, compiles the context module, and returns 0
     *
     * @throws BakedPathFound
     * @throws RuntimeException
     */
    #[Test]
    public function runCleansCompilesAndReturnsZero(): void
    {
        mkdir($this->meta->compileDir, permissions: 0o755, recursive: true);
        file_put_contents("{$this->meta->compileDir}/stale.php", data: '<?php return 0;');

        $status = $this->runner->run('prod', $this->meta);

        static::assertSame(0, $status);
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
     * @throws UnknownEnv
     */
    #[Test]
    public function resolvesFromReadOnlyCompileDir(): void
    {
        $this->runner->run('prod', $this->meta);
        chmod($this->meta->compileDir, permissions: 0o555);
        $before = $this->snapshot($this->meta->compileDir);

        $injector = (new MapContextProvider(['prod' => FakeProdContext::class]))->get(
            'prod',
            $this->meta,
        )->getInjectorInstance();

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
     * @throws UnknownEnv
     */
    #[Test]
    public function resolvesWithoutCompileTimeTmpDir(): void
    {
        $this->runner->run('prod', $this->meta);
        Fs::removeDir("{$this->baseDir}/app/var/tmp");
        $runtimeMeta = new AppMeta(
            'fake',
            "{$this->baseDir}/app",
            $this->meta->compileDir,
            "{$this->baseDir}/absent-tmp",
        );

        $injector = (new MapContextProvider(['prod' => FakeProdContext::class]))->get(
            'prod',
            $runtimeMeta,
        )->getInjectorInstance();

        static::assertInstanceOf(FakeCar::class, $injector->getInstance(FakeCarInterface::class));
    }

    /**
     * The guard runs after compilation and rejects baked runtime paths
     *
     * @throws RuntimeException
     */
    #[Test]
    public function runGuardsBakedPathAfterCompile(): void
    {
        try {
            $this->runner->run('baked', $this->meta);
            static::fail('BakedPathFound was not thrown');
        } catch (BakedPathFound $e) {
            static::assertStringContainsString($this->meta->appDir, $e->getMessage());
            // The compiled scripts exist: compilation preceded the guard
            static::assertNotSame([], glob("{$this->meta->compileDir}/*.php"));
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
}

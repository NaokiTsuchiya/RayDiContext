<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\BakedPathFound;
use NaokiTsuchiya\RayDiContext\Exception\CompileFailed;
use NaokiTsuchiya\RayDiContext\Exception\ExceptionInterface;
use NaokiTsuchiya\RayDiContext\Exception\UnsafeCompileDir;
use NaokiTsuchiya\RayDiContext\Fake\FakeBakedContext;
use NaokiTsuchiya\RayDiContext\Fake\FakeProdContext;
use NaokiTsuchiya\RayDiContext\Fake\FakeRecordingBakedPathGuard;
use NaokiTsuchiya\RayDiContext\Fake\FakeRejectingGuard;
use NaokiTsuchiya\RayDiContext\Fake\FakeThrowingCompiler;
use NaokiTsuchiya\RayDiContext\Fake\FakeUnboundContext;
use NaokiTsuchiya\RayDiContext\Support\AppDirFixture;
use NaokiTsuchiya\RayDiContext\Support\CompiledTree;
use NaokiTsuchiya\RayDiContext\Support\Fs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ray\Compiler\Exception\Unbound;
use RuntimeException;

use function chmod;
use function file_put_contents;
use function glob;
use function is_dir;
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
    public function runMakesCompiledScriptsWorldReadable(): void
    {
        $this->runner->run($this->meta);

        static::assertSame(0o755, Fs::mode($this->meta->compileDir));
        static::assertNotSame([], CompiledTree::assertWorldReadable($this->meta->compileDir));
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

    /** @throws ExceptionInterface */
    #[Test]
    public function runUsesTheInjectedBakedPathGuard(): void
    {
        $guard = new FakeRecordingBakedPathGuard();
        $runner = new CompileRunner(new MapContextProvider([
            'prod' => FakeProdContext::class,
        ]), bakedPathGuard: $guard);

        $runner->run($this->meta);

        static::assertTrue($guard->called);
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function runHonoursTheInjectedCompileDirGuard(): void
    {
        $runner = new CompileRunner(new MapContextProvider([
            'prod' => FakeProdContext::class,
        ]), compileDirGuard: new FakeRejectingGuard());

        try {
            $runner->run($this->meta);
            static::fail('UnsafeCompileDir was not thrown');
        } catch (UnsafeCompileDir) {
            static::assertDirectoryDoesNotExist($this->meta->compileDir);
        }
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function runWrapsAnyCompilerExceptionInCompileFailed(): void
    {
        $runner = new CompileRunner(new MapContextProvider([
            'prod' => FakeProdContext::class,
        ]), compiler: new FakeThrowingCompiler());

        try {
            $runner->run($this->meta);
            static::fail('CompileFailed was not thrown');
        } catch (CompileFailed $e) {
            static::assertInstanceOf(RuntimeException::class, $e->getPrevious());
            static::assertSame([], glob("{$this->meta->compileDir}/*"));
        }
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function runWrapsARealCompileFailureFromAnUnboundDependency(): void
    {
        $unboundMeta = new AppMeta($this->meta->appDir, 'unbound', $this->meta->compileDir, $this->meta->tmpDir);
        $runner = new CompileRunner(new MapContextProvider(['unbound' => FakeUnboundContext::class]));

        try {
            $runner->run($unboundMeta);
            static::fail('CompileFailed was not thrown');
        } catch (CompileFailed $e) {
            static::assertInstanceOf(Unbound::class, $e->getPrevious());
            static::assertSame([], glob("{$this->meta->compileDir}/*"));
        }
    }
}

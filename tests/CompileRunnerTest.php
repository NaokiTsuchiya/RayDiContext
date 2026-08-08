<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\CompileFailed;
use NaokiTsuchiya\RayDiContext\Exception\ExceptionInterface;
use NaokiTsuchiya\RayDiContext\Exception\UnknownContext;
use NaokiTsuchiya\RayDiContext\Exception\UnsafeCompileDir;
use NaokiTsuchiya\RayDiContext\Fake\FakeNestingCompiler;
use NaokiTsuchiya\RayDiContext\Fake\FakeProdContext;
use NaokiTsuchiya\RayDiContext\Fake\FakeRecordingCompiler;
use NaokiTsuchiya\RayDiContext\Fake\FakeRejectingGuard;
use NaokiTsuchiya\RayDiContext\Fake\FakeThrowingCompiler;
use NaokiTsuchiya\RayDiContext\Fake\FakeThrowingContext;
use NaokiTsuchiya\RayDiContext\Support\AppDirFixture;
use NaokiTsuchiya\RayDiContext\Support\CompiledTree;
use NaokiTsuchiya\RayDiContext\Support\Fs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function array_filter;
use function dirname;
use function glob;
use function str_ends_with;

/** Steps run() takes without ever reaching a real compile; CompileRunnerIntegrationTest covers the rest */
#[CoversClass(CompileRunner::class)]
final class CompileRunnerTest extends TestCase
{
    /** Working directory and meta shared by the tests in this class */
    private AppDirFixture $fixture;

    /** Meta with conventional paths under the app dir */
    private AppMeta $meta;

    /** @throws ExceptionInterface */
    protected function setUp(): void
    {
        $this->fixture = new AppDirFixture('runner_');
        $this->meta = $this->fixture->meta;
    }

    /** {@inheritDoc} */
    protected function tearDown(): void
    {
        $this->fixture->remove();
    }

    /**
     * A ContextInterface::__invoke() failure is not a compiler failure, so it must not be wrapped
     *
     * @throws ExceptionInterface
     */
    #[Test]
    public function runDoesNotWrapAContextResolutionFailure(): void
    {
        $throwingMeta = new AppMeta($this->meta->appDir, 'throwing', $this->meta->compileDir, $this->meta->tmpDir);
        $runner = new CompileRunner(new MapContextProvider(['throwing' => FakeThrowingContext::class]));

        try {
            $runner->run($throwingMeta);
            static::fail('RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            static::assertNotInstanceOf(CompileFailed::class, $e);
            static::assertSame([], glob("{$this->meta->compileDir}/*"));
        }
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function resolvesTheContextBeforeEmptyingTheCompileDir(): void
    {
        $this->seedStaleScript();
        $compiler = new FakeRecordingCompiler();
        $runner = new CompileRunner(new MapContextProvider([
            'prod' => FakeProdContext::class,
        ]), compiler: $compiler);
        $unknown = new AppMeta($this->meta->appDir, 'nosuch', $this->meta->compileDir, $this->meta->tmpDir);

        try {
            $runner->run($unknown);
            static::fail('UnknownContext was not thrown');
        } catch (UnknownContext) {
            static::assertFileExists("{$this->meta->compileDir}/stale.php");
            static::assertFalse($compiler->called);
        }
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function emptiesTheCompileDirBeforeCompiling(): void
    {
        $this->seedStaleScript();
        $compiler = new FakeRecordingCompiler();
        $runner = new CompileRunner(new MapContextProvider([
            'prod' => FakeProdContext::class,
        ]), compiler: $compiler);

        $runner->run($this->meta);

        static::assertTrue($compiler->called);
        static::assertSame([], $compiler->entriesWhenCalled);
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function runRejectsUnsafeCompileDirBeforeCleaning(): void
    {
        $appDir = $this->meta->appDir;
        $unsafeMeta = new AppMeta($appDir, 'prod', $appDir, $this->meta->tmpDir);
        $runner = new CompileRunner(new MapContextProvider(['prod' => FakeProdContext::class]));

        try {
            $runner->run($unsafeMeta);
            static::fail('UnsafeCompileDir was not thrown');
        } catch (UnsafeCompileDir) {
            static::assertDirectoryExists($this->meta->tmpDir);
        }
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
    public function runCleansUpWhenTheBakedPathGuardRejects(): void
    {
        $runner = new CompileRunner(
            new MapContextProvider(['prod' => FakeProdContext::class]),
            bakedPathGuard: new FakeRejectingGuard(),
            compiler: new FakeRecordingCompiler(),
        );

        try {
            $runner->run($this->meta);
            static::fail('UnsafeCompileDir was not thrown');
        } catch (UnsafeCompileDir) {
            static::assertSame([], glob("{$this->meta->compileDir}/*"));
        }
    }

    /**
     * A compile whose output is not flat is normalized all the way down
     *
     * @throws ExceptionInterface
     */
    #[Test]
    public function runNormalizesAScriptCompiledIntoASubdirectory(): void
    {
        $runner = new CompileRunner(new MapContextProvider([
            'prod' => FakeProdContext::class,
        ]), compiler: new FakeNestingCompiler());

        $runner->run($this->meta);

        $compileDir = $this->meta->compileDir;
        $nested = array_filter(
            CompiledTree::assertWorldReadable($compileDir),
            static fn(string $path): bool => str_ends_with($path, '.php') && $compileDir !== dirname($path),
        );

        static::assertNotSame([], $nested);
    }

    /** Seeds a stale script only the ordering-sensitive tests need, so it cannot bias the others */
    private function seedStaleScript(): void
    {
        Fs::copyFile(Fs::SCRIPT, "{$this->meta->compileDir}/stale.php");
    }
}

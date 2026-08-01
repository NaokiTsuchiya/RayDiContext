<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\ExceptionInterface;
use NaokiTsuchiya\RayDiContext\Exception\UnknownContext;
use NaokiTsuchiya\RayDiContext\Fake\FakeProdContext;
use NaokiTsuchiya\RayDiContext\Fake\FakeRecordingCompiler;
use NaokiTsuchiya\RayDiContext\Support\AppDirFixture;
use NaokiTsuchiya\RayDiContext\Support\Fs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The order of the pipeline's steps, which is a guarantee in its own right
 */
#[CoversClass(CompileRunner::class)]
final class CompileRunnerOrderingTest extends TestCase
{
    /** Stands in for a script from a previous compile */
    private const SCRIPT = __DIR__ . '/Fixture/script.php';

    /** Working directory and meta shared by the compile test classes */
    private AppDirFixture $fixture;

    /** Meta with conventional paths under the app dir */
    private AppMeta $meta;

    /** Stands in for ray/compiler so the compile dir can be observed mid-run */
    private FakeRecordingCompiler $compiler;

    /** @throws ExceptionInterface */
    protected function setUp(): void
    {
        $this->fixture = new AppDirFixture('runner_order_');
        $this->meta = $this->fixture->meta;
        $this->compiler = new FakeRecordingCompiler();
        Fs::copyFile(self::SCRIPT, "{$this->meta->compileDir}/stale.php");
    }

    /** {@inheritDoc} */
    protected function tearDown(): void
    {
        $this->fixture->remove();
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function resolvesTheContextBeforeEmptyingTheCompileDir(): void
    {
        $runner = new CompileRunner(new MapContextProvider([
            'prod' => FakeProdContext::class,
        ]), compiler: $this->compiler);
        $unknown = new AppMeta($this->meta->appDir, 'nosuch', $this->meta->compileDir, $this->meta->tmpDir);

        try {
            $runner->run($unknown);
            static::fail('UnknownContext was not thrown');
        } catch (UnknownContext) {
            static::assertFileExists("{$this->meta->compileDir}/stale.php");
            static::assertFalse($this->compiler->called);
        }
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function emptiesTheCompileDirBeforeCompiling(): void
    {
        $runner = new CompileRunner(new MapContextProvider([
            'prod' => FakeProdContext::class,
        ]), compiler: $this->compiler);

        $runner->run($this->meta);

        static::assertTrue($this->compiler->called);
        static::assertSame([], $this->compiler->entriesWhenCalled);
    }
}

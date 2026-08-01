<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\ExceptionInterface;
use NaokiTsuchiya\RayDiContext\Exception\UnknownContext;
use NaokiTsuchiya\RayDiContext\Fake\FakeProdContext;
use NaokiTsuchiya\RayDiContext\Fake\FakeRecordingCompiler;
use NaokiTsuchiya\RayDiContext\Fake\Fs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function copy;
use function mkdir;
use function uniqid;

/**
 * The order of the pipeline's steps, which is a guarantee in its own right
 *
 * Swapping the first two lines of run(), or wrapping the clean in a try/finally, leaves every
 * other test green while turning a mistyped context name into an emptied compile dir with
 * nothing written back into it. These cases pin the order down directly.
 */
#[CoversClass(CompileRunner::class)]
final class CompileRunnerOrderingTest extends TestCase
{
    /** Stands in for a script from a previous compile */
    private const SCRIPT = __DIR__ . '/Fixture/script.php';

    /** @var non-empty-string Per-test working directory */
    private string $baseDir;

    /** Meta with conventional paths under the app dir */
    private AppMeta $meta;

    /** Stands in for ray/compiler so the compile dir can be observed mid-run */
    private FakeRecordingCompiler $compiler;

    /** @throws ExceptionInterface */
    protected function setUp(): void
    {
        $this->baseDir = __DIR__ . '/tmp/' . uniqid('runner_order_', more_entropy: true);
        $appDir = "{$this->baseDir}/app";
        mkdir("{$appDir}/var/tmp/prod", permissions: 0o755, recursive: true);
        $this->meta = AppMeta::fromAppDir($appDir, 'prod');
        $this->compiler = new FakeRecordingCompiler();
        mkdir($this->meta->compileDir, permissions: 0o755, recursive: true);
        copy(self::SCRIPT, "{$this->meta->compileDir}/stale.php");
    }

    /** {@inheritDoc} */
    protected function tearDown(): void
    {
        Fs::removeDir($this->baseDir);
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

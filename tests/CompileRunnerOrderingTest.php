<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\ContextClassNotFound;
use NaokiTsuchiya\RayDiContext\Exception\InvalidAppMeta;
use NaokiTsuchiya\RayDiContext\Exception\InvalidContextClass;
use NaokiTsuchiya\RayDiContext\Exception\UnknownContext;
use NaokiTsuchiya\RayDiContext\Fake\FakeProdContext;
use NaokiTsuchiya\RayDiContext\Fake\FakeRecordingCompiler;
use NaokiTsuchiya\RayDiContext\Fake\Fs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function file_put_contents;
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
    /** @var non-empty-string Per-test working directory */
    private string $baseDir;

    /** Meta with conventional paths under the app dir */
    private AppMeta $meta;

    /** Stands in for ray/compiler so the compile dir can be observed mid-run */
    private FakeRecordingCompiler $compiler;

    /**
     * {@inheritDoc}
     *
     * @throws InvalidAppMeta
     */
    protected function setUp(): void
    {
        $this->baseDir = __DIR__ . '/tmp/' . uniqid('runner_order_', more_entropy: true);
        $appDir = "{$this->baseDir}/app";
        mkdir("{$appDir}/var/tmp/prod", permissions: 0o755, recursive: true);
        $this->meta = AppMeta::fromAppDir($appDir, 'prod');
        $this->compiler = new FakeRecordingCompiler();
        mkdir($this->meta->compileDir, permissions: 0o755, recursive: true);
        file_put_contents("{$this->meta->compileDir}/stale.php", data: '<?php return 0;');
    }

    /**
     * {@inheritDoc}
     */
    protected function tearDown(): void
    {
        Fs::removeDir($this->baseDir);
    }

    /**
     * An unknown context aborts with the compile dir untouched
     *
     * The context is resolved before the cleaner runs, so a typo in the context name costs
     * nothing. Were the two swapped, the compile dir would be emptied and then nothing written
     * back into it — the previous compile gone and no new one to replace it.
     *
     * @throws RuntimeException
     * @throws ContextClassNotFound
     * @throws InvalidContextClass
     * @throws InvalidAppMeta
     */
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

    /**
     * The compile dir is empty by the time the compiler is asked to write into it
     *
     * A recompile must not leave scripts of renamed or removed classes behind, and the only
     * moment that can be observed is while the compiler is running.
     *
     * @throws RuntimeException
     * @throws ContextClassNotFound
     * @throws InvalidContextClass
     */
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

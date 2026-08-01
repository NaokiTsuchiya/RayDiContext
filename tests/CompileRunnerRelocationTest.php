<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\ExceptionInterface;
use NaokiTsuchiya\RayDiContext\Fake\FakeCar;
use NaokiTsuchiya\RayDiContext\Fake\FakeCarInterface;
use NaokiTsuchiya\RayDiContext\Fake\FakeProdContext;
use NaokiTsuchiya\RayDiContext\Support\Fs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ray\Compiler\CompiledInjector;

use function mkdir;
use function uniqid;

/** A compileDir baked at one absolute path still resolves after moving to another */
#[CoversClass(CompileRunner::class)]
final class CompileRunnerRelocationTest extends TestCase
{
    /** Per-test working directory */
    private string $baseDir;

    /** Meta with conventional paths under the app dir */
    private AppMeta $meta;

    /** @throws ExceptionInterface */
    protected function setUp(): void
    {
        $this->baseDir = __DIR__ . '/tmp/' . uniqid('runner_relocation_', more_entropy: true);
        $appDir = "{$this->baseDir}/app";
        mkdir("{$appDir}/var/tmp/prod", permissions: 0o755, recursive: true);
        $this->meta = AppMeta::fromAppDir($appDir, 'prod');
    }

    /** {@inheritDoc} */
    protected function tearDown(): void
    {
        Fs::removeDir($this->baseDir);
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function resolvesAfterCompileDirMovesToADifferentAbsolutePath(): void
    {
        (new CompileRunner(new MapContextProvider(['prod' => FakeProdContext::class])))->run($this->meta);
        $relocatedCompileDir = "{$this->baseDir}/deployed/var/di/prod";
        Fs::copyDir($this->meta->compileDir, $relocatedCompileDir);
        Fs::removeDir($this->meta->compileDir);
        $relocatedMeta = new AppMeta($this->meta->appDir, 'prod', $relocatedCompileDir, $this->meta->tmpDir);

        $injector = (new MapContextProvider(['prod' => FakeProdContext::class]))->get(
            $relocatedMeta,
        )->getInjectorInstance();

        static::assertInstanceOf(CompiledInjector::class, $injector);
        static::assertInstanceOf(FakeCar::class, $injector->getInstance(FakeCarInterface::class));
    }
}

<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\ExceptionInterface;
use NaokiTsuchiya\RayDiContext\Fake\FakeCar;
use NaokiTsuchiya\RayDiContext\Fake\FakeCarInterface;
use NaokiTsuchiya\RayDiContext\Fake\FakeProdContext;
use NaokiTsuchiya\RayDiContext\Support\AppDirFixture;
use NaokiTsuchiya\RayDiContext\Support\Fs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ray\Compiler\CompiledInjector;

/** A compileDir baked at one absolute path still resolves after moving to another */
#[CoversClass(CompileRunner::class)]
final class CompileRunnerRelocationTest extends TestCase
{
    /** Working directory and meta shared by the compile test classes */
    private AppDirFixture $fixture;

    /** Meta with conventional paths under the app dir */
    private AppMeta $meta;

    /** @throws ExceptionInterface */
    protected function setUp(): void
    {
        $this->fixture = new AppDirFixture('runner_relocation_');
        $this->meta = $this->fixture->meta;
    }

    /** {@inheritDoc} */
    protected function tearDown(): void
    {
        $this->fixture->remove();
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function resolvesAfterCompileDirMovesToADifferentAbsolutePath(): void
    {
        (new CompileRunner(new MapContextProvider(['prod' => FakeProdContext::class])))->run($this->meta);
        $relocatedCompileDir = "{$this->fixture->baseDir}/deployed/var/di/prod";
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

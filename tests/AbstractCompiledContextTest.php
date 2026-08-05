<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\CompileDirUnavailable;
use NaokiTsuchiya\RayDiContext\Exception\ExceptionInterface;
use NaokiTsuchiya\RayDiContext\Fake\FakeCar;
use NaokiTsuchiya\RayDiContext\Fake\FakeCarInterface;
use NaokiTsuchiya\RayDiContext\Fake\FakeCompiledProdContext;
use NaokiTsuchiya\RayDiContext\Support\Fs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ray\Compiler\DiCompileModule;
use Ray\Compiler\Exception\ScriptDirNotReadable;
use Ray\Di\Injector;

use function uniqid;

/** Behaviour that does not need a real compile; AbstractCompiledContextIntegrationTest covers the compiled path */
#[CoversClass(AbstractCompiledContext::class)]
final class AbstractCompiledContextTest extends TestCase
{
    /** Per-test working directory */
    private string $baseDir;

    /** {@inheritDoc} */
    protected function setUp(): void
    {
        $this->baseDir = __DIR__ . '/tmp/' . uniqid('compiled_context_', more_entropy: true);
    }

    /** {@inheritDoc} */
    protected function tearDown(): void
    {
        Fs::removeDir($this->baseDir);
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function invokeComposesDiCompileModuleAroundAppModule(): void
    {
        $context = new FakeCompiledProdContext(new AppMeta('/app', 'prod', '/app/var/di/prod', '/app/var/tmp/prod'));

        $module = $context();

        static::assertInstanceOf(DiCompileModule::class, $module);
        static::assertInstanceOf(FakeCar::class, (new Injector($module))->getInstance(FakeCarInterface::class));
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function getInjectorInstanceThrowsCompileDirUnavailableForAMissingCompileDir(): void
    {
        $context = new FakeCompiledProdContext(
            new AppMeta($this->baseDir, 'prod', "{$this->baseDir}/absent-di", "{$this->baseDir}/tmp"),
        );

        try {
            $context->getInjectorInstance();
            static::fail('CompileDirUnavailable was not thrown');
        } catch (CompileDirUnavailable $e) {
            static::assertInstanceOf(ScriptDirNotReadable::class, $e->getPrevious());
        }
    }
}

<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\CompileDirUnavailable;
use NaokiTsuchiya\RayDiContext\Exception\ExceptionInterface;
use NaokiTsuchiya\RayDiContext\Exception\WarmupNotCompiled;
use NaokiTsuchiya\RayDiContext\Support\CompileDirFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ray\Compiler\Exception\ScriptDirNotReadable;
use Ray\Compiler\Exception\SingletonsFileNotFound;

/** What the injector reports without a real compile; CompiledWarmInjectorIntegrationTest covers the rest */
#[CoversClass(CompiledWarmInjector::class)]
final class CompiledWarmInjectorTest extends TestCase
{
    /** Working directory holding an empty compile dir */
    private CompileDirFixture $fixture;

    /** {@inheritDoc} */
    protected function setUp(): void
    {
        $this->fixture = new CompileDirFixture('warm_injector_');
    }

    /** {@inheritDoc} */
    protected function tearDown(): void
    {
        $this->fixture->remove();
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function constructorThrowsCompileDirUnavailableForAMissingCompileDir(): void
    {
        try {
            new CompiledWarmInjector("{$this->fixture->baseDir}/absent");
            static::fail('CompileDirUnavailable was not thrown');
        } catch (CompileDirUnavailable $e) {
            static::assertStringContainsString('absent', $e->getMessage());
            static::assertInstanceOf(ScriptDirNotReadable::class, $e->getPrevious());
        }
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function warmupThrowsWarmupNotCompiledWithoutSingletonMetadata(): void
    {
        $injector = new CompiledWarmInjector($this->fixture->compileDir);

        try {
            $injector->warmup();
            static::fail('WarmupNotCompiled was not thrown');
        } catch (WarmupNotCompiled $e) {
            static::assertStringContainsString($this->fixture->compileDir, $e->getMessage());
            static::assertInstanceOf(SingletonsFileNotFound::class, $e->getPrevious());
        }
    }
}

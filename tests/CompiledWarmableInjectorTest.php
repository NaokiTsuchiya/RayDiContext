<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\ExceptionInterface;
use NaokiTsuchiya\RayDiContext\Exception\WarmupNotCompiled;
use NaokiTsuchiya\RayDiContext\Support\CompileDirFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ray\Compiler\CompiledInjector;
use Ray\Compiler\Exception\SingletonsFileNotFound;

/** What the wrapper reports without a real compile; CompiledWarmableInjectorIntegrationTest covers the rest */
#[CoversClass(CompiledWarmableInjector::class)]
final class CompiledWarmableInjectorTest extends TestCase
{
    /** Working directory holding an empty compile dir */
    private CompileDirFixture $fixture;

    /** {@inheritDoc} */
    protected function setUp(): void
    {
        $this->fixture = new CompileDirFixture('compiled_warmable_');
    }

    /** {@inheritDoc} */
    protected function tearDown(): void
    {
        $this->fixture->remove();
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function warmupThrowsWarmupNotCompiledWithoutSingletonMetadata(): void
    {
        $injector = new CompiledWarmableInjector(new CompiledInjector($this->fixture->compileDir));

        try {
            $injector->warmup();
            static::fail('WarmupNotCompiled was not thrown');
        } catch (WarmupNotCompiled $e) {
            static::assertStringContainsString($this->fixture->compileDir, $e->getMessage());
            static::assertInstanceOf(SingletonsFileNotFound::class, $e->getPrevious());
        }
    }
}

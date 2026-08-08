<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\ExceptionInterface;
use NaokiTsuchiya\RayDiContext\Exception\WarmupNotCompiled;
use NaokiTsuchiya\RayDiContext\Fake\FakeModule;
use NaokiTsuchiya\RayDiContext\Fake\FakeWarmupProbe;
use NaokiTsuchiya\RayDiContext\Support\CompileDirFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ray\Compiler\CompiledInjector;
use Ray\Compiler\Exception\SingletonsFileNotFound;
use Ray\Di\Injector;

/** What the warmer does without a real compile; SingletonWarmerIntegrationTest covers the compiled path */
#[CoversClass(SingletonWarmer::class)]
final class SingletonWarmerTest extends TestCase
{
    /** Working directory holding an empty compile dir */
    private CompileDirFixture $fixture;

    /** System under test */
    private SingletonWarmer $warmer;

    /** {@inheritDoc} */
    protected function setUp(): void
    {
        $this->fixture = new CompileDirFixture('warmer_');
        $this->warmer = new SingletonWarmer();
        FakeWarmupProbe::reset();
    }

    /** {@inheritDoc} */
    protected function tearDown(): void
    {
        $this->fixture->remove();
    }

    /**
     * A dev context's runtime injector compiles as it resolves, so it has nothing to warm
     *
     * @throws ExceptionInterface
     */
    #[Test]
    public function invokeLeavesAnInjectorThatCompilesAtRuntimeAlone(): void
    {
        $injector = new Injector(new FakeModule(), $this->fixture->baseDir);

        ($this->warmer)($injector);

        static::assertSame(0, FakeWarmupProbe::constructed());
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function invokeThrowsWarmupNotCompiledWithoutSingletonMetadata(): void
    {
        $injector = new CompiledInjector($this->fixture->compileDir);

        try {
            ($this->warmer)($injector);
            static::fail('WarmupNotCompiled was not thrown');
        } catch (WarmupNotCompiled $e) {
            static::assertStringContainsString($this->fixture->compileDir, $e->getMessage());
            static::assertInstanceOf(SingletonsFileNotFound::class, $e->getPrevious());
        }
    }
}

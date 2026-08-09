<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\ExceptionInterface;
use NaokiTsuchiya\RayDiContext\Fake\FakeWarmupModule;
use NaokiTsuchiya\RayDiContext\Fake\FakeWarmupProbe;
use NaokiTsuchiya\RayDiContext\Support\CompileDirFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ray\Di\Injector;

#[CoversClass(RuntimeWarmableInjector::class)]
final class RuntimeWarmableInjectorTest extends TestCase
{
    /** Working directory the runtime injector compiles into */
    private CompileDirFixture $fixture;

    /** System under test, over a runtime injector whose module binds one singleton */
    private RuntimeWarmableInjector $injector;

    /** {@inheritDoc} */
    protected function setUp(): void
    {
        $this->fixture = new CompileDirFixture('runtime_warmable_');
        $this->injector = new RuntimeWarmableInjector(new Injector(new FakeWarmupModule(), $this->fixture->baseDir));
        FakeWarmupProbe::reset();
    }

    /** {@inheritDoc} */
    protected function tearDown(): void
    {
        $this->fixture->remove();
    }

    /**
     * A runtime injector compiles as it resolves, so even its singleton bindings stay cold
     *
     * @throws ExceptionInterface
     */
    #[Test]
    public function warmupLeavesEverySingletonBindingCold(): void
    {
        $this->injector->warmup();

        static::assertSame(0, FakeWarmupProbe::constructed());
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function getInstanceResolvesThroughTheRuntimeInjector(): void
    {
        $resolved = $this->injector->getInstance(FakeWarmupProbe::class);

        static::assertInstanceOf(FakeWarmupProbe::class, $resolved);
        static::assertSame(1, FakeWarmupProbe::constructed());
    }
}

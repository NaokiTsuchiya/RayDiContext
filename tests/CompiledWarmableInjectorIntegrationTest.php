<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\ExceptionInterface;
use NaokiTsuchiya\RayDiContext\Fake\FakeWarmupContext;
use NaokiTsuchiya\RayDiContext\Fake\FakeWarmupProbe;
use NaokiTsuchiya\RayDiContext\Support\AppDirFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ray\Compiler\CompiledInjector;

/** What the wrapper does against scripts a real ray/compiler run produced */
#[CoversClass(CompiledWarmableInjector::class)]
final class CompiledWarmableInjectorIntegrationTest extends TestCase
{
    /** Working directory and meta shared by the tests in this class */
    private AppDirFixture $fixture;

    /** System under test, over a freshly compiled dir carrying one singleton */
    private CompiledWarmableInjector $injector;

    /** @throws ExceptionInterface */
    protected function setUp(): void
    {
        $this->fixture = new AppDirFixture('compiled_warmable_integration_');
        (new CompileRunner(new MapContextProvider(['prod' => FakeWarmupContext::class])))->run($this->fixture->meta);
        $this->injector = new CompiledWarmableInjector(new CompiledInjector($this->fixture->meta->compileDir));
        FakeWarmupProbe::reset();
    }

    /** {@inheritDoc} */
    protected function tearDown(): void
    {
        $this->fixture->remove();
    }

    /**
     * The whole point of warming up: the singleton exists before anything asks for it
     *
     * @throws ExceptionInterface
     */
    #[Test]
    public function warmupInstantiatesTheSingletonBeforeAnythingResolvesIt(): void
    {
        static::assertSame(0, FakeWarmupProbe::constructed());

        $this->injector->warmup();

        static::assertSame(1, FakeWarmupProbe::constructed());
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function getInstanceResolvesTheWarmedSingletonFromTheCache(): void
    {
        $this->injector->warmup();

        $resolved = $this->injector->getInstance(FakeWarmupProbe::class);

        static::assertInstanceOf(FakeWarmupProbe::class, $resolved);
        static::assertSame(1, FakeWarmupProbe::constructed());
    }
}

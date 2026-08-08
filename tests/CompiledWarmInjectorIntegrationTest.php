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

/** What the injector does against scripts a real ray/compiler run produced */
#[CoversClass(CompiledWarmInjector::class)]
final class CompiledWarmInjectorIntegrationTest extends TestCase
{
    /** Working directory and meta shared by the tests in this class */
    private AppDirFixture $fixture;

    /** Meta with conventional paths under the app dir */
    private AppMeta $meta;

    /** @throws ExceptionInterface */
    protected function setUp(): void
    {
        $this->fixture = new AppDirFixture('warm_injector_integration_');
        $this->meta = $this->fixture->meta;
        (new CompileRunner(new MapContextProvider(['prod' => FakeWarmupContext::class])))->run($this->meta);
        FakeWarmupProbe::reset();
    }

    /** {@inheritDoc} */
    protected function tearDown(): void
    {
        $this->fixture->remove();
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function getInstanceResolvesThroughTheCompiledScripts(): void
    {
        $injector = new CompiledWarmInjector($this->meta->compileDir);

        static::assertInstanceOf(FakeWarmupProbe::class, $injector->getInstance(FakeWarmupProbe::class));
    }

    /**
     * The whole point of warming up: the singleton exists before anything asks for it
     *
     * @throws ExceptionInterface
     */
    #[Test]
    public function warmupInstantiatesTheSingletonBeforeAnythingResolvesIt(): void
    {
        $injector = new CompiledWarmInjector($this->meta->compileDir);
        static::assertSame(0, FakeWarmupProbe::constructed());

        $injector->warmup();

        static::assertSame(1, FakeWarmupProbe::constructed());
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function warmupLeavesTheWarmedSingletonCachedForLaterResolution(): void
    {
        $injector = new CompiledWarmInjector($this->meta->compileDir);
        $injector->warmup();

        $injector->getInstance(FakeWarmupProbe::class);

        static::assertSame(1, FakeWarmupProbe::constructed());
    }
}

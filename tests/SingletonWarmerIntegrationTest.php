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

/** What the warmer does to the injector the builder hands back for a compiled context */
#[CoversClass(SingletonWarmer::class)]
final class SingletonWarmerIntegrationTest extends TestCase
{
    /** Working directory and meta shared by the tests in this class */
    private AppDirFixture $fixture;

    /** The compiled context whose scripts the warmer runs against */
    private FakeWarmupContext $context;

    /** @throws ExceptionInterface */
    protected function setUp(): void
    {
        $this->fixture = new AppDirFixture('warmer_integration_');
        (new CompileRunner(new MapContextProvider(['prod' => FakeWarmupContext::class])))->run($this->fixture->meta);
        $this->context = new FakeWarmupContext($this->fixture->meta);
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
    public function invokeInstantiatesTheSingletonBeforeAnythingResolvesIt(): void
    {
        $injector = (new InjectorBuilder())($this->context, $this->fixture->meta);
        static::assertSame(0, FakeWarmupProbe::constructed());

        (new SingletonWarmer())($injector);

        static::assertSame(1, FakeWarmupProbe::constructed());
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function invokeLeavesTheWarmedSingletonCachedForLaterResolution(): void
    {
        $injector = (new InjectorBuilder())($this->context, $this->fixture->meta);
        (new SingletonWarmer())($injector);

        $resolved = $injector->getInstance(FakeWarmupProbe::class);

        static::assertInstanceOf(FakeWarmupProbe::class, $resolved);
        static::assertSame(1, FakeWarmupProbe::constructed());
    }
}

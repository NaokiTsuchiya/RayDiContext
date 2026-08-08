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

/** The injector the base class hands back, against scripts a real ray/compiler run produced */
#[CoversClass(AbstractWarmCompiledContext::class)]
final class AbstractWarmCompiledContextIntegrationTest extends TestCase
{
    /** Working directory and meta shared by the tests in this class */
    private AppDirFixture $fixture;

    /** System under test */
    private FakeWarmupContext $context;

    /** @throws ExceptionInterface */
    protected function setUp(): void
    {
        $this->fixture = new AppDirFixture('warm_context_integration_');
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
     * A line-coverage hit cannot tell a working injector from one that threw
     *
     * @throws ExceptionInterface
     */
    #[Test]
    public function getInjectorInstanceReturnsAWarmInjectorResolvingTheCompiledAppModule(): void
    {
        $injector = $this->context->getInjectorInstance();

        $injector->warmup();

        static::assertSame(1, FakeWarmupProbe::constructed());
        static::assertInstanceOf(FakeWarmupProbe::class, $injector->getInstance(FakeWarmupProbe::class));
    }
}

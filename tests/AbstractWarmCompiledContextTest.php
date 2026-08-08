<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\CompileDirUnavailable;
use NaokiTsuchiya\RayDiContext\Exception\ExceptionInterface;
use NaokiTsuchiya\RayDiContext\Fake\FakeWarmupContext;
use NaokiTsuchiya\RayDiContext\Support\AppDirFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ray\Compiler\DiCompileModule;

/** What the base class returns without a real compile; the resolution it produces is covered by CompiledWarmInjectorIntegrationTest */
#[CoversClass(AbstractWarmCompiledContext::class)]
final class AbstractWarmCompiledContextTest extends TestCase
{
    /** Working directory and meta shared by the tests in this class */
    private AppDirFixture $fixture;

    /** System under test */
    private FakeWarmupContext $context;

    /** @throws ExceptionInterface */
    protected function setUp(): void
    {
        $this->fixture = new AppDirFixture('warm_context_');
        $this->context = new FakeWarmupContext($this->fixture->meta);
    }

    /** {@inheritDoc} */
    protected function tearDown(): void
    {
        $this->fixture->remove();
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function invokeComposesTheCompileModuleAroundTheAppModule(): void
    {
        static::assertInstanceOf(DiCompileModule::class, ($this->context)());
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function getInjectorInstanceThrowsCompileDirUnavailableForAMissingCompileDir(): void
    {
        $this->expectException(CompileDirUnavailable::class);

        $this->context->getInjectorInstance();
    }
}

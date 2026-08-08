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

/** Behaviour that does not need a real compile; AbstractWarmCompiledContextIntegrationTest covers the compiled path */
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
    public function getInjectorInstanceThrowsCompileDirUnavailableForAMissingCompileDir(): void
    {
        $this->expectException(CompileDirUnavailable::class);

        $this->context->getInjectorInstance();
    }
}

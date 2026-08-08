<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\CompileDirUnavailable;
use NaokiTsuchiya\RayDiContext\Exception\ExceptionInterface;
use NaokiTsuchiya\RayDiContext\Fake\FakeCar;
use NaokiTsuchiya\RayDiContext\Fake\FakeCarInterface;
use NaokiTsuchiya\RayDiContext\Fake\FakeDevContext;
use NaokiTsuchiya\RayDiContext\Fake\FakeProdContext;
use NaokiTsuchiya\RayDiContext\Support\AppDirFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ray\Compiler\Exception\ScriptDirNotReadable;
use Ray\Di\Injector;

/** What the builder does without a real compile; InjectorBuilderIntegrationTest covers the compiled path */
#[CoversClass(InjectorBuilder::class)]
final class InjectorBuilderTest extends TestCase
{
    /** Working directory and meta shared by the tests in this class */
    private AppDirFixture $fixture;

    /** System under test */
    private InjectorBuilder $builder;

    /** @throws ExceptionInterface */
    protected function setUp(): void
    {
        $this->fixture = new AppDirFixture('builder_');
        $this->builder = new InjectorBuilder();
    }

    /** {@inheritDoc} */
    protected function tearDown(): void
    {
        $this->fixture->remove();
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function buildsARuntimeInjectorForAContextWithoutTheCompiledMarker(): void
    {
        $context = new FakeDevContext($this->fixture->meta);

        $injector = ($this->builder)($context, $this->fixture->meta);

        static::assertInstanceOf(Injector::class, $injector);
        static::assertInstanceOf(FakeCar::class, $injector->getInstance(FakeCarInterface::class));
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function throwsCompileDirUnavailableForACompiledContextWithoutACompileDir(): void
    {
        $context = new FakeProdContext($this->fixture->meta);

        try {
            ($this->builder)($context, $this->fixture->meta);
            static::fail('CompileDirUnavailable was not thrown');
        } catch (CompileDirUnavailable $e) {
            static::assertStringContainsString($this->fixture->meta->compileDir, $e->getMessage());
            static::assertInstanceOf(ScriptDirNotReadable::class, $e->getPrevious());
        }
    }
}

<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\ExceptionInterface;
use NaokiTsuchiya\RayDiContext\Fake\FakeQualifiedContext;
use NaokiTsuchiya\RayDiContext\Support\AppDirFixture;
use NaokiTsuchiya\RayDiContext\Support\CompiledTree;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_filter;
use function dirname;
use function str_ends_with;

/** A compile whose output is not flat is normalized all the way down */
#[CoversClass(CompileRunner::class)]
final class CompileRunnerNestedScriptTest extends TestCase
{
    /** Working directory and meta shared by the compile test classes */
    private AppDirFixture $fixture;

    /** Meta with conventional paths under the app dir */
    private AppMeta $meta;

    /** @throws ExceptionInterface */
    protected function setUp(): void
    {
        $this->fixture = new AppDirFixture('runner_nested_');
        $this->meta = $this->fixture->meta;
    }

    /** {@inheritDoc} */
    protected function tearDown(): void
    {
        $this->fixture->remove();
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function normalizesAScriptCompiledIntoASubdirectory(): void
    {
        (new CompileRunner(new MapContextProvider(['prod' => FakeQualifiedContext::class])))->run($this->meta);

        $compileDir = $this->meta->compileDir;
        $nested = array_filter(
            CompiledTree::assertWorldReadable($compileDir),
            static fn(string $path): bool => str_ends_with($path, '.php') && $compileDir !== dirname($path),
        );

        static::assertNotSame([], $nested);
    }
}

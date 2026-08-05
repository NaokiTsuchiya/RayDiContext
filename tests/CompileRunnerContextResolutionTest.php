<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\CompileFailed;
use NaokiTsuchiya\RayDiContext\Exception\ExceptionInterface;
use NaokiTsuchiya\RayDiContext\Fake\FakeThrowingContext;
use NaokiTsuchiya\RayDiContext\Support\AppDirFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function glob;

/**
 * A ContextInterface::__invoke() failure is not a compiler failure, so it must not be wrapped
 */
#[CoversClass(CompileRunner::class)]
final class CompileRunnerContextResolutionTest extends TestCase
{
    /** Working directory and meta shared by the compile test classes */
    private AppDirFixture $fixture;

    /** Meta with conventional paths under the app dir */
    private AppMeta $meta;

    /** @throws ExceptionInterface */
    protected function setUp(): void
    {
        $this->fixture = new AppDirFixture('runner_context_');
        $this->meta = $this->fixture->meta;
    }

    /** {@inheritDoc} */
    protected function tearDown(): void
    {
        $this->fixture->remove();
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function runDoesNotWrapAContextResolutionFailure(): void
    {
        $throwingMeta = new AppMeta($this->meta->appDir, 'throwing', $this->meta->compileDir, $this->meta->tmpDir);
        $runner = new CompileRunner(new MapContextProvider(['throwing' => FakeThrowingContext::class]));

        try {
            $runner->run($throwingMeta);
            static::fail('RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            static::assertNotInstanceOf(CompileFailed::class, $e);
            static::assertSame([], glob("{$this->meta->compileDir}/*"));
        }
    }
}

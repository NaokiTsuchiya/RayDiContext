<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\ExceptionInterface;
use NaokiTsuchiya\RayDiContext\Fake\FakeCompiledProdContext;
use NaokiTsuchiya\RayDiContext\Support\Fs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ray\Compiler\CompiledInjector;
use Ray\Compiler\DiCompileModule;
use Ray\Compiler\Exception\ScriptDirNotReadable;

use function mkdir;
use function uniqid;

#[CoversClass(AbstractCompiledContext::class)]
final class AbstractCompiledContextTest extends TestCase
{
    /** Per-test working directory */
    private string $baseDir;

    /** {@inheritDoc} */
    protected function setUp(): void
    {
        $this->baseDir = __DIR__ . '/tmp/' . uniqid('compiled_context_', more_entropy: true);
    }

    /** {@inheritDoc} */
    protected function tearDown(): void
    {
        Fs::removeDir($this->baseDir);
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function invokeComposesDiCompileModuleAroundAppModule(): void
    {
        $context = new FakeCompiledProdContext(new AppMeta('/app', 'prod', '/app/var/di/prod', '/app/var/tmp/prod'));

        static::assertInstanceOf(DiCompileModule::class, $context());
    }

    /**
     * @throws ExceptionInterface
     * @throws ScriptDirNotReadable
     */
    #[Test]
    public function getInjectorInstanceReturnsCompiledInjectorForCompileDir(): void
    {
        $compileDir = "{$this->baseDir}/di";
        mkdir($compileDir, permissions: 0o755, recursive: true);
        $context = new FakeCompiledProdContext(
            new AppMeta($this->baseDir, 'prod', $compileDir, "{$this->baseDir}/tmp"),
        );

        static::assertInstanceOf(CompiledInjector::class, $context->getInjectorInstance());
    }
}

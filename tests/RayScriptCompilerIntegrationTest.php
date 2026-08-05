<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Fake\FakeModule;
use NaokiTsuchiya\RayDiContext\Support\Fs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ray\Compiler\DiCompileModule;

use function glob;
use function mkdir;
use function uniqid;

/** The bundled compiler writes where it is told */
#[CoversClass(RayScriptCompiler::class)]
final class RayScriptCompilerIntegrationTest extends TestCase
{
    /** @var non-empty-string Per-test working directory */
    private string $baseDir;

    /** {@inheritDoc} */
    protected function setUp(): void
    {
        $this->baseDir = __DIR__ . '/tmp/' . uniqid('ray_compiler_', more_entropy: true);
    }

    /** {@inheritDoc} */
    protected function tearDown(): void
    {
        Fs::removeDir($this->baseDir);
    }

    /** The module's bindings are compiled into the given directory */
    #[Test]
    public function compilesTheModuleIntoTheCompileDir(): void
    {
        $compileDir = "{$this->baseDir}/di";
        mkdir($compileDir, permissions: 0o755, recursive: true);

        (new RayScriptCompiler())->compile(new DiCompileModule(true, new FakeModule()), $compileDir);

        static::assertNotSame([], glob("{$compileDir}/*FakeCarInterface*.php"));
    }
}

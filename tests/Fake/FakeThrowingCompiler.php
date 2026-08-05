<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext\Fake;

use NaokiTsuchiya\RayDiContext\ScriptCompilerInterface;
use Ray\Di\AbstractModule;
use RuntimeException;

/** Compiler stub that always fails, regardless of what it is asked to compile */
final class FakeThrowingCompiler implements ScriptCompilerInterface
{
    /** @throws RuntimeException Always. */
    public function compile(AbstractModule $module, string $compileDir): void
    {
        throw new RuntimeException('Fake compiler blew up');
    }
}

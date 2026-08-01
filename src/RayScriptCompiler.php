<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use Ray\Compiler\Compiler;
use Ray\Di\AbstractModule;

/**
 * The bundled compiler, delegating to ray/compiler
 *
 * @api
 */
final class RayScriptCompiler implements ScriptCompilerInterface
{
    /** {@inheritDoc} */
    public function compile(AbstractModule $module, string $compileDir): void
    {
        (new Compiler())->compile($module, $compileDir);
    }
}

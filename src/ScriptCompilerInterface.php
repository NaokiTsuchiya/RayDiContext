<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use Ray\Di\AbstractModule;

/**
 * Compiles a module into a directory of scripts
 *
 * The seam over ray/compiler, so the pipeline's ordering can be asserted without a real compile.
 * Implementations pass their compiler's exceptions through unchanged.
 *
 * @api
 */
interface ScriptCompilerInterface
{
    /**
     * Writes the compiled scripts of $module into $compileDir
     *
     * @param non-empty-string $compileDir
     */
    public function compile(AbstractModule $module, string $compileDir): void;
}

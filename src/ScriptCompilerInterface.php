<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use Ray\Di\AbstractModule;

/**
 * Compiles a module into a directory of scripts
 *
 * The seam over ray/compiler. Implementations pass their compiler's exceptions through unchanged —
 * that binds compile() itself; a caller going through CompileRunner::run() instead sees whatever an
 * implementation throws wrapped in Exception\CompileFailed, the original retrievable via
 * getPrevious().
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

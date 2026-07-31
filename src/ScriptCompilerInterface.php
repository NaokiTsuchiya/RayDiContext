<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use Ray\Di\AbstractModule;

/**
 * Compiles a module into a directory of scripts
 *
 * The seam over ray/compiler. It exists so the pipeline's ordering guarantees — the compile dir
 * is verified before it is emptied, the scripts are guarded after they are written, permissions
 * are normalized only once the guard passed — can be asserted directly instead of being inferred
 * from the side effects of a real compile.
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

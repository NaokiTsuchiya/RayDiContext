<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use Ray\Compiler\Compiler;
use Ray\Di\AbstractModule;

/**
 * The bundled compiler, delegating to ray/compiler
 *
 * Kept to one line so that everything this package knows about ray/compiler's compile call sits
 * in one place: PermissionNormalizer already exists to work around how that call writes, and a
 * change upstream should have somewhere obvious to land.
 *
 * @api
 */
final class RayScriptCompiler implements ScriptCompilerInterface
{
    /**
     * {@inheritDoc}
     */
    public function compile(AbstractModule $module, string $compileDir): void
    {
        (new Compiler())->compile($module, $compileDir);
    }
}

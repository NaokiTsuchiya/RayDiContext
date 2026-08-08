<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\CompileDirUnavailable;
use Override;

/**
 * Base context for the ahead-of-time compiled production shape, with singleton warmup
 *
 * Same as AbstractCompiledContext except that getInjectorInstance() returns a
 * CompiledWarmInjector, so a caller holding the result can warm the compiled singletons before
 * serving anything. Extend this one rather than AbstractCompiledContext unless a return type of
 * exactly Ray\Compiler\CompiledInjector is what the application wants.
 *
 * @api
 */
abstract class AbstractWarmCompiledContext extends AbstractCompiledContext
{
    /**
     * {@inheritDoc}
     *
     * @throws CompileDirUnavailable When $meta->compileDir does not exist or is not readable
     *         at construction time. Wraps ray/compiler's own ScriptDirNotReadable, retrievable
     *         via getPrevious().
     */
    #[Override]
    public function getInjectorInstance(): CompiledWarmInjector
    {
        return new CompiledWarmInjector($this->meta->compileDir);
    }
}

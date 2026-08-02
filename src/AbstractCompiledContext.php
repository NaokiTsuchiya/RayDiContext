<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\CompileDirUnavailable;
use Ray\Compiler\CompiledInjector;
use Ray\Compiler\DiCompileModule;
use Ray\Compiler\Exception\ScriptDirNotReadable;
use Ray\Di\AbstractModule;
use Ray\Di\InjectorInterface;

use function sprintf;

/**
 * Base context for the ahead-of-time compiled production shape
 *
 * __invoke() composes DiCompileModule(true, appModule()); getInjectorInstance() returns
 * CompiledInjector($meta->compileDir), wrapping the directory check ray/compiler runs at
 * construction so a consumer only ever needs to catch this package's ExceptionInterface.
 * Implement appModule() with the application's own module — nothing else needs a
 * Ray\Compiler class name.
 *
 * Neither method is final, so a subclass may override one to use a different injector;
 * nothing here enforces that the two stay consistent if only one is overridden.
 *
 * @api
 */
abstract class AbstractCompiledContext extends AbstractContext
{
    /** Returns the application module to compile ahead of time */
    abstract protected function appModule(): AbstractModule;

    /** {@inheritDoc} */
    public function __invoke(): AbstractModule
    {
        return new DiCompileModule(true, $this->appModule());
    }

    /**
     * {@inheritDoc}
     *
     * @throws CompileDirUnavailable When $meta->compileDir does not exist or is not readable
     *         at construction time. Wraps ray/compiler's own ScriptDirNotReadable, retrievable
     *         via getPrevious().
     */
    public function getInjectorInstance(): InjectorInterface
    {
        try {
            return new CompiledInjector($this->meta->compileDir);
        } catch (ScriptDirNotReadable $e) {
            throw new CompileDirUnavailable(
                sprintf('Compile dir does not exist or is not readable: "%s"', $this->meta->compileDir),
                previous: $e,
            );
        }
    }
}

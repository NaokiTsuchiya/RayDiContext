<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use Ray\Compiler\CompiledInjector;
use Ray\Compiler\DiCompileModule;
use Ray\Compiler\Exception\ScriptDirNotReadable;
use Ray\Di\AbstractModule;
use Ray\Di\InjectorInterface;

/**
 * Base context for the ahead-of-time compiled production shape
 *
 * __invoke() composes DiCompileModule(true, appModule()); getInjectorInstance() returns
 * CompiledInjector($meta->compileDir). Implement appModule() with the application's own
 * module — nothing else needs a Ray\Compiler class name.
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
     * @throws ScriptDirNotReadable When $meta->compileDir does not exist or is not
     *         readable at construction time. Thrown by ray/compiler; it does not
     *         implement this package's Exception\ExceptionInterface.
     */
    public function getInjectorInstance(): InjectorInterface
    {
        return new CompiledInjector($this->meta->compileDir);
    }
}

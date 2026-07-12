<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use Ray\Di\AbstractModule;
use Ray\Di\InjectorInterface;

/**
 * Application context
 *
 * A context maps an environment to a module, an injector, and process-start singletons.
 *
 * @api
 */
interface ContextInterface
{
    /**
     * Returns the application module of this context
     *
     * A context that is compiled ahead of time composes DiCompileModule with the
     * application module.
     */
    public function __invoke(): AbstractModule;

    /**
     * Returns the injector of this context
     *
     * A production context returns CompiledInjector($meta->compileDir); a development
     * context returns Ray\Di\Injector.
     */
    public function getInjectorInstance(): InjectorInterface;

    /**
     * Returns classes to instantiate once at process start under the real environment
     *
     * These singletons are freshly instantiated, never unserialized, so they may hold
     * runtime resources such as database connections. The singleton scope is per
     * injector instance: they are shared only through the injector that created them.
     *
     * @return list<class-string>
     */
    public function getSavedSingleton(): array;
}

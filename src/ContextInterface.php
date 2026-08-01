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
     *
     * Whether repeated calls return the same instance is not part of this contract. Call
     * it once per process and reuse the result: warming up getSavedSingleton() against one
     * instance and then serving requests from another defeats the warmup.
     */
    public function getInjectorInstance(): InjectorInterface;

    /**
     * Returns classes to instantiate once at process start under the real environment
     *
     * Freshly instantiated, never unserialized, so they may hold runtime resources such as
     * database connections. The scope is per injector instance.
     *
     * @return list<class-string>
     */
    public function getSavedSingleton(): array;
}

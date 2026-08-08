<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

/**
 * Names the classes an application instantiates once at process start
 *
 * @api
 */
interface SavedSingletonInterface
{
    /**
     * Returns classes to instantiate once at process start under the real environment
     *
     * Freshly instantiated, never unserialized, so they may hold runtime resources such as
     * database connections. The scope is per injector instance.
     *
     * @return list<class-string>
     *
     * @deprecated Superseded by SingletonWarmer, which instantiates every singleton the compile
     *             recorded instead of the ones this list names.
     */
    public function getSavedSingleton(): array;
}

<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use Ray\Di\AbstractModule;

/**
 * Application context
 *
 * A context supplies the application module of one environment, and nothing else. Which
 * injector resolves that module is not the context's business: InjectorBuilder decides it
 * from whether the context carries CompiledContextInterface.
 *
 * @api
 */
interface ContextInterface
{
    /**
     * Returns the application module of this context
     */
    public function __invoke(): AbstractModule;
}

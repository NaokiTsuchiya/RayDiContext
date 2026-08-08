<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use Ray\Di\AbstractModule;
use Ray\Di\InjectorInterface;

/**
 * Application context
 *
 * @api
 */
interface ContextInterface extends SavedSingletonInterface
{
    /**
     * Returns the application module of this context
     */
    public function __invoke(): AbstractModule;

    /**
     * Returns the injector of this context
     *
     * Whether repeated calls return the same instance is not part of this contract. Call
     * it once per process and reuse the result: warming one instance up and then serving
     * requests from another defeats the warmup.
     */
    public function getInjectorInstance(): InjectorInterface;
}

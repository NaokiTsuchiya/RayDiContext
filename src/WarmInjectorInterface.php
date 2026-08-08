<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\ExceptionInterface;
use Ray\Di\InjectorInterface;

/**
 * An injector that can instantiate its singletons before anything asks for one
 *
 * @api
 */
interface WarmInjectorInterface extends InjectorInterface
{
    /**
     * Instantiates every singleton this injector can build without a caller
     *
     * Call once per process, on the instance that will serve requests: the instances are cached
     * in the injector, so warming one and serving from another warms nothing. A binding that
     * needs an injection point is not a singleton the compiler accepts, so nothing here can
     * depend on who asked first.
     *
     * @throws ExceptionInterface
     */
    public function warmup(): void;
}

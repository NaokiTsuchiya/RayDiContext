<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\WarmupNotCompiled;
use Ray\Di\InjectorInterface;

/**
 * An injector that can bring its singletons to their served state before anything asks
 *
 * warmup() is a call the runtime bootstrap makes by hand, once per process, on the instance
 * that will serve requests — singletons are cached per injector instance, so warming one
 * instance up and then serving from another warms nothing. Resolving InjectorInterface
 * through the container returns the underlying injector, which does not carry warmup();
 * warm the instance InjectorBuilder returned.
 *
 * @api
 */
interface WarmableInjectorInterface extends InjectorInterface
{
    /**
     * Instantiates every singleton this injector can build before it serves anything
     *
     * An injector with nothing to warm returns quietly.
     *
     * @throws WarmupNotCompiled When the compiled scripts record no singleton metadata.
     */
    public function warmup(): void;
}

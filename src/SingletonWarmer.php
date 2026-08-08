<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\WarmupNotCompiled;
use Ray\Compiler\CompiledInjector;
use Ray\Compiler\Exception\SingletonsFileNotFound;
use Ray\Di\InjectorInterface;

use function sprintf;

/**
 * Instantiates the singletons of an ahead-of-time compiled injector, before anything asks for one
 *
 * @api
 */
final class SingletonWarmer
{
    /**
     * Warms up every singleton the compile recorded
     *
     * Call once per process, on the instance that will serve requests: the instances are cached
     * in the injector, so warming one and serving from another warms nothing. An injector that
     * compiles at runtime has no such metadata and nothing to race over, so it is left alone.
     *
     * @param InjectorInterface $injector The injector this process will serve requests from
     *
     * @throws WarmupNotCompiled When the compiled scripts record no singleton metadata. Wraps
     *         ray/compiler's own SingletonsFileNotFound, retrievable via getPrevious().
     */
    public function __invoke(InjectorInterface $injector): void
    {
        $isCompiled = $injector instanceof CompiledInjector;
        if (!$isCompiled) {
            return;
        }

        try {
            $injector->warmup();
        } catch (SingletonsFileNotFound $e) {
            throw new WarmupNotCompiled(
                sprintf('Compiled scripts hold no singleton metadata to warm up: %s', $e->getMessage()),
                previous: $e,
            );
        }
    }
}

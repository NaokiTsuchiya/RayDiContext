<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\WarmupNotCompiled;
use Override;
use Ray\Compiler\CompiledInjector;
use Ray\Compiler\Exception\SingletonsFileNotFound;
use Ray\Di\Name;

use function sprintf;

/**
 * The warmable face of an ahead-of-time compiled injector
 *
 * @api
 */
final class CompiledWarmableInjector implements WarmableInjectorInterface
{
    /** @param CompiledInjector $injector The compiled injector every call delegates to */
    public function __construct(
        private readonly CompiledInjector $injector,
    ) {}

    /**
     * {@inheritDoc}
     *
     * @param ''|class-string<T> $interface
     * @param string             $name
     *
     * @return ($interface is '' ? mixed : T)
     *
     * @template T of object
     */
    #[Override]
    public function getInstance($interface, $name = Name::ANY)
    {
        return $this->injector->getInstance($interface, $name);
    }

    /**
     * {@inheritDoc}
     *
     * @throws WarmupNotCompiled When the compiled scripts record no singleton metadata — they
     *         were compiled by something older than ray/compiler 1.15. Wraps ray/compiler's
     *         own SingletonsFileNotFound, retrievable via getPrevious().
     */
    #[Override]
    public function warmup(): void
    {
        try {
            $this->injector->warmup();
        } catch (SingletonsFileNotFound $e) {
            throw new WarmupNotCompiled(
                sprintf('Compiled scripts hold no singleton metadata to warm up: %s', $e->getMessage()),
                previous: $e,
            );
        }
    }
}

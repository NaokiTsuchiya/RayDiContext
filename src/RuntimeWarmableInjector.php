<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use Override;
use Ray\Di\InjectorInterface;
use Ray\Di\Name;

/**
 * The warmable face of a runtime injector, which has nothing to warm
 *
 * @api
 */
final class RuntimeWarmableInjector implements WarmableInjectorInterface
{
    /** @param InjectorInterface $injector The runtime injector every call delegates to */
    public function __construct(
        private readonly InjectorInterface $injector,
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

    /** {@inheritDoc} */
    #[Override]
    public function warmup(): void {}
}

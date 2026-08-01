<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

/**
 * Base context holding the application meta
 *
 * The constructor is final because a provider instantiates a context it only knows by
 * name — MapContextProvider does `new $class($meta)` — and a subclass that widened or
 * reordered it would turn that call into a runtime fatal.
 *
 * @api
 */
abstract class AbstractContext implements ContextInterface
{
    /**
     * @param AppMeta $meta Application metadata
     */
    final public function __construct(
        protected readonly AppMeta $meta,
    ) {}

    /** {@inheritDoc} */
    public function getSavedSingleton(): array
    {
        return [];
    }
}

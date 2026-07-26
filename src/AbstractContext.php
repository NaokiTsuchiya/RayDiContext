<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

/**
 * Base context holding the application meta
 *
 * A context provider instantiates a context class it only knows by name, as
 * MapContextProvider does with new $class($meta). The constructor is final so that
 * signature stays true for every subclass: a subclass that widened or reordered it
 * would turn that call into a runtime fatal.
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

    /**
     * {@inheritDoc}
     */
    public function getSavedSingleton(): array
    {
        return [];
    }
}

<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

/**
 * Base context holding the application meta
 *
 * The constructor is final: a provider instantiates a context by name, so a widened one
 * would turn `new $class($meta)` into a runtime fatal. Nothing in mago can pin this.
 *
 * @api
 */
abstract class AbstractContext implements ContextInterface
{
    /** @param AppMeta $meta Application metadata */
    final public function __construct(
        protected readonly AppMeta $meta,
    ) {}

    /** {@inheritDoc} */
    public function getSavedSingleton(): array
    {
        return [];
    }
}

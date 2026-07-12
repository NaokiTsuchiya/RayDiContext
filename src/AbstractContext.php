<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

/**
 * Base context holding the application meta
 *
 * @psalm-consistent-constructor
 *
 * @api
 */
abstract class AbstractContext implements ContextInterface
{
    /**
     * @param AppMeta $meta Application metadata
     */
    public function __construct(
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

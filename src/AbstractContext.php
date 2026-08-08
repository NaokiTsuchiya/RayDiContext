<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

/**
 * Base context holding the application meta
 *
 * @api
 */
abstract class AbstractContext implements ContextInterface
{
    use SavedSingletonTrait;

    /** @param AppMeta $meta Application metadata */
    final public function __construct(
        protected readonly AppMeta $meta,
    ) {}
}

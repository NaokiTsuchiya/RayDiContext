<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

/**
 * Provides the context bound to an AppMeta's context name
 *
 * @api
 */
interface ContextProviderInterface
{
    /** Returns the context bound to $meta->context */
    public function get(AppMeta $meta): ContextInterface;
}

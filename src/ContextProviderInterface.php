<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

/**
 * Provides the context bound to an env name
 *
 * The env-to-context mapping belongs to the application.
 *
 * @api
 */
interface ContextProviderInterface
{
    /**
     * Returns the context bound to the given env
     */
    public function get(string $env, AppMeta $meta): ContextInterface;
}

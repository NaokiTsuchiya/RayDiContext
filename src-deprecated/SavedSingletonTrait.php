<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

/**
 * The default SavedSingletonInterface implementation AbstractContext carries
 *
 * @internal Extend AbstractContext rather than using this directly
 */
trait SavedSingletonTrait
{
    /**
     * {@inheritDoc}
     *
     * @return list<class-string>
     */
    public function getSavedSingleton(): array
    {
        return [];
    }
}

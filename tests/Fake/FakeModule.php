<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext\Fake;

use Ray\Di\AbstractModule;

/**
 * Fake application module
 */
final class FakeModule extends AbstractModule
{
    /**
     * {@inheritDoc}
     */
    protected function configure(): void
    {
        $this->bind(FakeCarInterface::class)->to(FakeCar::class);
    }
}

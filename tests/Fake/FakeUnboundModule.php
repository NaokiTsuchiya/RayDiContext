<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext\Fake;

use Ray\Di\AbstractModule;

/** Fake module reproducing a compile-time Unbound failure */
final class FakeUnboundModule extends AbstractModule
{
    /** {@inheritDoc} */
    protected function configure(): void
    {
        $this->bind(FakeNeedsUnbound::class);
    }
}

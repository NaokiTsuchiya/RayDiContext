<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext\Fake;

use Ray\Di\AbstractModule;
use Ray\Di\Scope;

/** Fake module whose one binding is a singleton, so the compiler records it as warmable */
final class FakeWarmupModule extends AbstractModule
{
    /** {@inheritDoc} */
    protected function configure(): void
    {
        $this->bind(FakeWarmupProbe::class)->in(Scope::SINGLETON);
    }
}

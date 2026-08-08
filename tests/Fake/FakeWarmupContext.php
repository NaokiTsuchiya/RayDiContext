<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext\Fake;

use NaokiTsuchiya\RayDiContext\AbstractWarmCompiledContext;
use Ray\Di\AbstractModule;

/** Fake warmable compiled context whose module carries a singleton to warm up */
final class FakeWarmupContext extends AbstractWarmCompiledContext
{
    /** {@inheritDoc} */
    protected function appModule(): AbstractModule
    {
        return new FakeWarmupModule();
    }
}

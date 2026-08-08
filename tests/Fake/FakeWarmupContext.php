<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext\Fake;

use NaokiTsuchiya\RayDiContext\AbstractCompiledContext;
use Ray\Di\AbstractModule;

/** Fake compiled context whose module carries a singleton to warm up */
final class FakeWarmupContext extends AbstractCompiledContext
{
    /** {@inheritDoc} */
    protected function appModule(): AbstractModule
    {
        return new FakeWarmupModule();
    }
}

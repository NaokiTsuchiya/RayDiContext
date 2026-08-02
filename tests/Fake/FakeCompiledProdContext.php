<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext\Fake;

use NaokiTsuchiya\RayDiContext\AbstractCompiledContext;
use Ray\Di\AbstractModule;

/** Fake ahead-of-time compiled context exercising AbstractCompiledContext's defaults */
final class FakeCompiledProdContext extends AbstractCompiledContext
{
    /** {@inheritDoc} */
    protected function appModule(): AbstractModule
    {
        return new FakeModule();
    }
}

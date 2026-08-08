<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext\Fake;

use NaokiTsuchiya\RayDiContext\AbstractContext;
use NaokiTsuchiya\RayDiContext\CompiledContextInterface;
use Ray\Di\AbstractModule;

/** Fake compiled context whose module carries a singleton to warm up */
final class FakeWarmupContext extends AbstractContext implements CompiledContextInterface
{
    /** {@inheritDoc} */
    public function __invoke(): AbstractModule
    {
        return new FakeWarmupModule();
    }
}

<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext\Fake;

use NaokiTsuchiya\RayDiContext\AbstractContext;
use Ray\Di\AbstractModule;

/** Fake context whose module fails to compile because of an unbound dependency */
final class FakeUnboundContext extends AbstractContext
{
    /** {@inheritDoc} */
    public function __invoke(): AbstractModule
    {
        return new FakeUnboundModule();
    }
}

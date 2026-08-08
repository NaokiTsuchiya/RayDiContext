<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext\Fake;

use NaokiTsuchiya\RayDiContext\AbstractContext;
use Ray\Di\AbstractModule;

/** Fake context whose module bakes runtime paths into the compiled scripts */
final class FakeBakedContext extends AbstractContext
{
    /** {@inheritDoc} */
    public function __invoke(): AbstractModule
    {
        return new FakeBakedModule($this->meta);
    }
}

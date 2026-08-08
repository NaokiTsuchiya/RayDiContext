<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext\Fake;

use NaokiTsuchiya\RayDiContext\AbstractContext;
use NaokiTsuchiya\RayDiContext\CompiledContextInterface;
use Ray\Di\AbstractModule;

/** Fake production context compiled ahead of time */
final class FakeProdContext extends AbstractContext implements CompiledContextInterface
{
    /** {@inheritDoc} */
    public function __invoke(): AbstractModule
    {
        return new FakeModule();
    }
}

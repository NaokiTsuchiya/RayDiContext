<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext\Fake;

use NaokiTsuchiya\RayDiContext\AbstractContext;
use NaokiTsuchiya\RayDiContext\AppMeta;
use Ray\Di\AbstractModule;

/** Fake development context resolved by the runtime injector */
final class FakeDevContext extends AbstractContext
{
    /** {@inheritDoc} */
    public function __invoke(): AbstractModule
    {
        return new FakeModule();
    }

    /** Exposes the injected meta for assertions */
    public function getMeta(): AppMeta
    {
        return $this->meta;
    }
}

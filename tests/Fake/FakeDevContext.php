<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext\Fake;

use NaokiTsuchiya\RayDiContext\AbstractContext;
use NaokiTsuchiya\RayDiContext\AppMeta;
use Ray\Di\AbstractModule;
use Ray\Di\Injector;
use Ray\Di\InjectorInterface;

/**
 * Fake development context using the runtime injector
 */
final class FakeDevContext extends AbstractContext
{
    /**
     * {@inheritDoc}
     */
    public function __invoke(): AbstractModule
    {
        return new FakeModule();
    }

    /**
     * {@inheritDoc}
     */
    public function getInjectorInstance(): InjectorInterface
    {
        return new Injector($this(), $this->meta->tmpDir);
    }

    /**
     * Exposes the injected meta for assertions
     */
    public function getMeta(): AppMeta
    {
        return $this->meta;
    }
}

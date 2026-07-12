<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext\Fake;

use NaokiTsuchiya\RayDiContext\AbstractContext;
use Ray\Compiler\CompiledInjector;
use Ray\Compiler\DiCompileModule;
use Ray\Di\AbstractModule;
use Ray\Di\InjectorInterface;

/**
 * Fake production context compiled ahead of time
 */
final class FakeProdContext extends AbstractContext
{
    /**
     * {@inheritDoc}
     */
    public function __invoke(): AbstractModule
    {
        return new DiCompileModule(true, new FakeModule());
    }

    /**
     * {@inheritDoc}
     */
    public function getInjectorInstance(): InjectorInterface
    {
        return new CompiledInjector($this->meta->compileDir);
    }

    /**
     * {@inheritDoc}
     */
    public function getSavedSingleton(): array
    {
        return [FakeCarInterface::class];
    }
}

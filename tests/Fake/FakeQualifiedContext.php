<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext\Fake;

use NaokiTsuchiya\RayDiContext\AbstractContext;
use Ray\Compiler\CompiledInjector;
use Ray\Compiler\DiCompileModule;
use Ray\Di\AbstractModule;
use Ray\Di\InjectorInterface;

/**
 * Fake context that compiles a binding into a subdirectory of the compile dir
 */
final class FakeQualifiedContext extends AbstractContext
{
    /**
     * {@inheritDoc}
     */
    public function __invoke(): AbstractModule
    {
        return new DiCompileModule(true, new FakeQualifiedModule());
    }

    /**
     * {@inheritDoc}
     */
    public function getInjectorInstance(): InjectorInterface
    {
        return new CompiledInjector($this->meta->compileDir);
    }
}

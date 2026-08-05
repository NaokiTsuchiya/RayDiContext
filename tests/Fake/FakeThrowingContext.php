<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext\Fake;

use NaokiTsuchiya\RayDiContext\AbstractContext;
use Ray\Compiler\CompiledInjector;
use Ray\Di\AbstractModule;
use Ray\Di\InjectorInterface;
use RuntimeException;

/** Fake context whose module resolution fails before the compiler ever runs */
final class FakeThrowingContext extends AbstractContext
{
    /** @throws RuntimeException Always. */
    public function __invoke(): AbstractModule
    {
        throw new RuntimeException('Fake context blew up');
    }

    /** {@inheritDoc} */
    public function getInjectorInstance(): InjectorInterface
    {
        return new CompiledInjector($this->meta->compileDir);
    }
}

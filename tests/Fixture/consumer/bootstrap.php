<?php

declare(strict_types=1);

/**
 * Bootstrap of the consumer project tests/dist-check.sh installs.
 *
 * The consumer only gets this package's production autoloading, so it cannot reach
 * tests/Fake; everything the compile needs is declared here.
 */

use NaokiTsuchiya\RayDiContext\AbstractContext;
use NaokiTsuchiya\RayDiContext\MapContextProvider;
use Ray\Compiler\CompiledInjector;
use Ray\Compiler\DiCompileModule;
use Ray\Di\AbstractModule;
use Ray\Di\InjectorInterface;

interface ConsumerCarInterface
{
}

final class ConsumerCar implements ConsumerCarInterface
{
}

final class ConsumerModule extends AbstractModule
{
    protected function configure(): void
    {
        $this->bind(ConsumerCarInterface::class)->to(ConsumerCar::class);
    }
}

final class ConsumerProdContext extends AbstractContext
{
    public function __invoke(): AbstractModule
    {
        return new DiCompileModule(true, new ConsumerModule());
    }

    public function getInjectorInstance(): InjectorInterface
    {
        return new CompiledInjector($this->meta->compileDir);
    }
}

return new MapContextProvider(['prod' => ConsumerProdContext::class]);

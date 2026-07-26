<?php

declare(strict_types=1);

/**
 * Bootstrap for the consumer project ConsumerInstallTest builds.
 *
 * The consumer installs this package the way `composer require` does, so it has none of
 * this repository's dev autoloading and cannot reach tests/Fake. Everything the compile
 * needs is therefore declared here, in the global namespace.
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

<?php

declare(strict_types=1);

use NaokiTsuchiya\RayDiContext\AbstractWarmCompiledContext;
use NaokiTsuchiya\RayDiContext\MapContextProvider;
use Ray\Di\AbstractModule;

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

final class ConsumerProdContext extends AbstractWarmCompiledContext
{
    protected function appModule(): AbstractModule
    {
        return new ConsumerModule();
    }
}

return new MapContextProvider(['prod' => ConsumerProdContext::class]);

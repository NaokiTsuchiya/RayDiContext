<?php

declare(strict_types=1);

use NaokiTsuchiya\RayDiContext\AbstractContext;
use NaokiTsuchiya\RayDiContext\CompiledContextInterface;
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

final class ConsumerProdContext extends AbstractContext implements CompiledContextInterface
{
    public function __invoke(): AbstractModule
    {
        return new ConsumerModule();
    }
}

return new MapContextProvider(['prod' => ConsumerProdContext::class]);

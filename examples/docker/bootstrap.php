<?php

declare(strict_types=1);

/**
 * Bootstrap for the buildable Docker example — see examples/docker/Dockerfile and README.md's
 * "Deploying to Docker / Kubernetes" section. Unlike examples/bootstrap.php, this one defines a
 * concrete, working module rather than placeholder context classes, so the Dockerfile compiles
 * and runs it as-is.
 */

use NaokiTsuchiya\RayDiContext\AbstractWarmCompiledContext;
use NaokiTsuchiya\RayDiContext\MapContextProvider;
use Ray\Di\AbstractModule;

interface GreeterInterface
{
    public function greet(): string;
}

final class Greeter implements GreeterInterface
{
    public function greet(): string
    {
        return 'hello from the compiled injector';
    }
}

final class GreeterModule extends AbstractModule
{
    protected function configure(): void
    {
        $this->bind(GreeterInterface::class)->to(Greeter::class);
    }
}

final class ExampleProdContext extends AbstractWarmCompiledContext
{
    protected function appModule(): AbstractModule
    {
        return new GreeterModule();
    }
}

return new MapContextProvider(['prod' => ExampleProdContext::class]);

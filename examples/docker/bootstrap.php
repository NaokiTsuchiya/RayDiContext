<?php

declare(strict_types=1);

/**
 * Bootstrap for the buildable Docker example — see examples/docker/Dockerfile and README.md's
 * "Deploying to Docker / Kubernetes" section. Unlike examples/bootstrap.php, this one defines a
 * concrete, working module rather than placeholder context classes, so the Dockerfile compiles
 * and runs it as-is.
 */

use NaokiTsuchiya\RayDiContext\AbstractContext;
use NaokiTsuchiya\RayDiContext\MapContextProvider;
use Ray\Compiler\CompiledInjector;
use Ray\Compiler\DiCompileModule;
use Ray\Di\AbstractModule;
use Ray\Di\InjectorInterface;

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

final class ExampleProdContext extends AbstractContext
{
    public function __invoke(): AbstractModule
    {
        return new DiCompileModule(true, new GreeterModule());
    }

    public function getInjectorInstance(): InjectorInterface
    {
        return new CompiledInjector($this->meta->compileDir);
    }
}

return new MapContextProvider(['prod' => ExampleProdContext::class]);

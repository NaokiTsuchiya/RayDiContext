<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\CompileDirUnavailable;
use Ray\Compiler\CompiledInjector;
use Ray\Compiler\Exception\ScriptDirNotReadable;
use Ray\Di\Injector;
use Ray\Di\InjectorInterface;

use function sprintf;

/**
 * Builds the injector that serves a context under this process
 *
 * A context carrying CompiledContextInterface gets a CompiledInjector over the read-only
 * $meta->compileDir; any other context gets a runtime injector compiling into
 * $meta->tmpDir as it resolves. Build once per process and reuse the result: singletons
 * are cached per instance, so warming one injector up and serving requests from another
 * warms nothing.
 *
 * @api
 */
final class InjectorBuilder
{
    /**
     * @param ContextInterface $context The context this process serves
     * @param AppMeta          $meta    Application metadata naming the directories
     *
     * @throws CompileDirUnavailable When the context is compiled and $meta->compileDir does
     *         not exist or is not readable. Wraps ray/compiler's own ScriptDirNotReadable,
     *         retrievable via getPrevious().
     */
    public function __invoke(ContextInterface $context, AppMeta $meta): InjectorInterface
    {
        $isCompiled = $context instanceof CompiledContextInterface;
        if (!$isCompiled) {
            return new Injector($context(), $meta->tmpDir);
        }

        try {
            return new CompiledInjector($meta->compileDir);
        } catch (ScriptDirNotReadable $e) {
            throw new CompileDirUnavailable(
                sprintf('Compile dir does not exist or is not readable: "%s"', $meta->compileDir),
                previous: $e,
            );
        }
    }
}

<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\CompileDirUnavailable;
use Ray\Compiler\CompiledInjector;
use Ray\Compiler\Exception\ScriptDirNotReadable;
use Ray\Di\Injector;

use function sprintf;

/**
 * Builds the injector that serves a context under this process
 *
 * A context carrying CompiledContextInterface gets a CompiledInjector over the read-only
 * $meta->compileDir; any other context gets a runtime injector compiling into
 * $meta->tmpDir as it resolves.
 *
 * @api
 */
final class InjectorBuilder
{
    /**
     * @param ContextInterface $context The context this process serves
     * @param AppMeta          $meta    Application metadata naming the directories
     *
     * @throws CompileDirUnavailable When a compiled context's $meta->compileDir is missing or
     *         unreadable.
     */
    public function __invoke(ContextInterface $context, AppMeta $meta): WarmableInjectorInterface
    {
        $isCompiled = $context instanceof CompiledContextInterface;
        if (!$isCompiled) {
            return new RuntimeWarmableInjector(new Injector($context(), $meta->tmpDir));
        }

        try {
            return new CompiledWarmableInjector(new CompiledInjector($meta->compileDir));
        } catch (ScriptDirNotReadable $e) {
            throw new CompileDirUnavailable(
                sprintf('Compile dir does not exist or is not readable: "%s"', $meta->compileDir),
                previous: $e,
            );
        }
    }
}

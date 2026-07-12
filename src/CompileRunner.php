<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\BakedPathFound;
use Ray\Compiler\Compiler;
use RuntimeException;

/**
 * Compiles the context of an env into the compile dir
 *
 * @api
 */
final class CompileRunner
{
    /**
     * @param ContextProviderInterface $contextProvider Application env-to-context mapping
     * @param Cleaner                  $cleaner         Recreates the compile dir before compiling
     * @param BakedPathGuard           $guard           Verifies the compiled scripts afterwards
     */
    public function __construct(
        private readonly ContextProviderInterface $contextProvider,
        private readonly Cleaner $cleaner = new Cleaner(),
        private readonly BakedPathGuard $guard = new BakedPathGuard(),
    ) {}

    /**
     * Cleans the compile dir, compiles the context module, then guards against baked paths
     *
     * @return int Exit status
     *
     * @throws BakedPathFound When a compiled script contains an appDir or tmpDir literal.
     * @throws RuntimeException When the compile dir cannot be recreated or a script cannot be read.
     */
    public function run(string $env, AppMeta $meta): int
    {
        $context = $this->contextProvider->get($env, $meta);
        ($this->cleaner)($meta->compileDir);
        (new Compiler())->compile($context(), $meta->compileDir);
        ($this->guard)($meta->compileDir, $meta);

        return 0;
    }
}

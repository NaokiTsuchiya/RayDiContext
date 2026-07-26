<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\BakedPathFound;
use NaokiTsuchiya\RayDiContext\Exception\CompileDirNotWritable;
use NaokiTsuchiya\RayDiContext\Exception\RemoveFailed;
use NaokiTsuchiya\RayDiContext\Exception\ScriptNotReadable;
use NaokiTsuchiya\RayDiContext\Exception\UnsafeCompileDir;
use Ray\Compiler\Compiler;

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
     * @throws BakedPathFound When a compiled script contains an appDir or tmpDir literal.
     * @throws UnsafeCompileDir When the compile dir is the filesystem root or holds the app dir.
     * @throws CompileDirNotWritable When the compile dir does not exist and cannot be created.
     * @throws RemoveFailed When an entry inside the compile dir cannot be removed.
     * @throws ScriptNotReadable When a compiled script cannot be read.
     */
    public function run(AppMeta $meta): void
    {
        $context = $this->contextProvider->get($meta);
        ($this->cleaner)($meta);
        (new Compiler())->compile($context(), $meta->compileDir);
        ($this->guard)($meta->compileDir, $meta);
    }
}

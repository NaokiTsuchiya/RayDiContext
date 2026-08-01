<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\BakedPathFound;
use NaokiTsuchiya\RayDiContext\Exception\ChmodFailed;
use NaokiTsuchiya\RayDiContext\Exception\CompileDirNotFound;
use NaokiTsuchiya\RayDiContext\Exception\CompileDirNotReadable;
use NaokiTsuchiya\RayDiContext\Exception\CompileDirNotWritable;
use NaokiTsuchiya\RayDiContext\Exception\RemoveFailed;
use NaokiTsuchiya\RayDiContext\Exception\ScriptNotReadable;
use NaokiTsuchiya\RayDiContext\Exception\UnsafeCompileDir;

/**
 * Compiles the context of an env into the compile dir
 *
 * @api
 */
final class CompileRunner
{
    /**
     * @param ContextProviderInterface $contextProvider Application env-to-context mapping
     * @param CompileDirGuardInterface $compileDirGuard Rejects a compile dir that must not be emptied
     * @param BakedPathGuardInterface  $bakedPathGuard  Verifies the compiled scripts afterwards
     * @param ScriptCompilerInterface  $compiler        Writes the scripts
     */
    public function __construct(
        private readonly ContextProviderInterface $contextProvider,
        private readonly CompileDirGuardInterface $compileDirGuard = new CompileDirGuard(),
        private readonly BakedPathGuardInterface $bakedPathGuard = new BakedPathGuard(),
        private readonly ScriptCompilerInterface $compiler = new RayScriptCompiler(),
    ) {}

    /**
     * Cleans the compile dir, compiles the context module, guards against baked paths,
     * then normalizes the permissions of what was written
     *
     * A rejected compile leaves the compile dir empty, so scripts the guard refused cannot be
     * baked into an image by a later COPY.
     *
     * @throws BakedPathFound When a compiled script contains an appDir or tmpDir literal.
     * @throws UnsafeCompileDir When the compile dir is the filesystem root or holds the app dir.
     * @throws CompileDirNotWritable When the compile dir does not exist and cannot be created.
     * @throws RemoveFailed When an entry inside the compile dir cannot be removed.
     * @throws ScriptNotReadable When a compiled script cannot be read.
     * @throws ChmodFailed When a compiled script cannot be made readable.
     * @throws CompileDirNotFound When the compile dir is gone by the time it is guarded or normalized.
     * @throws CompileDirNotReadable When a directory in the compile dir cannot be listed or traversed.
     */
    public function run(AppMeta $meta): void
    {
        // Resolved before the cleaner runs, so an unknown context leaves the compile dir intact.
        $context = $this->contextProvider->get($meta);
        $cleaner = new Cleaner($this->compileDirGuard);
        $cleaner($meta);

        // A flag and finally rather than catch-and-rethrow: a rethrow types as the marker
        // interface and would widen the precise @throws list above.
        $guarded = false;
        try {
            $this->compiler->compile($context(), $meta->compileDir);
            ($this->bakedPathGuard)($meta);
            $guarded = true;
        } finally {
            if (!$guarded) {
                $cleaner($meta);
            }
        }

        (new PermissionNormalizer())($meta->compileDir);
    }
}

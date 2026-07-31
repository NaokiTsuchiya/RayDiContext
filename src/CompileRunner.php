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
     * @param ContextProviderInterface  $contextProvider Application env-to-context mapping
     * @param Cleaner                   $cleaner         Empties the compile dir before compiling
     * @param BakedPathGuardInterface   $guard           Verifies the compiled scripts afterwards
     * @param ScriptCompilerInterface   $compiler        Writes the scripts
     */
    public function __construct(
        private readonly ContextProviderInterface $contextProvider,
        private readonly Cleaner $cleaner = new Cleaner(),
        private readonly BakedPathGuardInterface $guard = new BakedPathGuard(),
        private readonly ScriptCompilerInterface $compiler = new RayScriptCompiler(),
    ) {}

    /**
     * Cleans the compile dir, compiles the context module, guards against baked paths,
     * then normalizes the permissions of what was written
     *
     * A rejected compile leaves the compile dir empty. Without that the scripts the guard just
     * refused stayed on disk, fully formed, for the next COPY to bake into the image; the only
     * thing making them unusable was that the normalizer had not run, so they were still 0600 —
     * a property of how ray/compiler happens to write today, and one PermissionNormalizer exists
     * to stop depending on. Emptying says it outright instead. The failure is reported either
     * way, and it names the file and the literal, so nothing diagnosable is lost with the
     * scripts.
     *
     * The normalizer is built here rather than injected: it is a fix for how ray/compiler
     * writes, not a policy an application chooses.
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
        // The context is resolved before the cleaner runs, so an unknown context aborts with the
        // compile dir untouched. That ordering is the only thing standing between a mistyped
        // context name and an emptied compile dir with nothing written back into it.
        $context = $this->contextProvider->get($meta);
        ($this->cleaner)($meta);

        // Emptied through a flag and finally rather than catch-and-rethrow: a rethrow is typed as
        // the marker interface, which would widen this method's declared throws from the precise
        // list below back to "anything this package throws".
        $guarded = false;
        try {
            $this->compiler->compile($context(), $meta->compileDir);
            ($this->guard)($meta);
            $guarded = true;
        } finally {
            if (!$guarded) {
                ($this->cleaner)($meta);
            }
        }

        (new PermissionNormalizer())($meta->compileDir);
    }
}

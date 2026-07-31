<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\BakedPathFound;
use NaokiTsuchiya\RayDiContext\Exception\CompileDirNotFound;
use NaokiTsuchiya\RayDiContext\Exception\CompileDirNotReadable;
use NaokiTsuchiya\RayDiContext\Exception\ScriptNotReadable;

/**
 * Decides whether what was just compiled may be shipped
 *
 * The bundled BakedPathGuard rejects the two runtime paths this package knows about. An
 * application knows things it does not — its own secrets, its own host names — and the README
 * is explicit that nothing else is checked: a value bound with toInstance() is frozen into the
 * compiled script, and only appDir and tmpDir are looked for. Passing an implementation to the
 * CompileRunner is how that gap gets closed, the same way CompileDirGuardInterface lets an
 * application refuse more compile dirs than the bundled guard does.
 *
 * @api
 */
interface BakedPathGuardInterface
{
    /**
     * Returns normally when the compiled scripts may be shipped
     *
     * @throws BakedPathFound When a compiled script contains a literal that must not ship.
     * @throws CompileDirNotFound When the compile dir is not an existing directory.
     * @throws CompileDirNotReadable When the compile dir, or a directory below it, cannot be
     *                                listed or traversed.
     * @throws ScriptNotReadable When a compiled script cannot be read.
     */
    public function __invoke(AppMeta $meta): void;
}

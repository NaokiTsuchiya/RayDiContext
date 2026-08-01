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
 * The bundled guard only knows appDir and tmpDir. An application knows its own secrets and host
 * names, and a value bound with toInstance() is frozen into the compiled script either way.
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

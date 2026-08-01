<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\UnsafeCompileDir;

/**
 * Decides whether a compile dir may have its contents removed
 *
 * The bundled guard rejects what can only ever be a configuration mistake; an application
 * that knows its own layout can reject more.
 *
 * @api
 */
interface CompileDirGuardInterface
{
    /**
     * Returns normally when the compile dir may be emptied
     *
     * @throws UnsafeCompileDir When the compile dir must never be emptied.
     */
    public function __invoke(AppMeta $meta): void;
}

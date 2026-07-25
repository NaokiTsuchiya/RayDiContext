<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext\Fake;

use NaokiTsuchiya\RayDiContext\AppMeta;
use NaokiTsuchiya\RayDiContext\CompileDirGuardInterface;
use NaokiTsuchiya\RayDiContext\Exception\UnsafeCompileDir;

/**
 * Application-supplied guard that rejects every compile dir
 */
final class FakeRejectingGuard implements CompileDirGuardInterface
{
    /**
     * {@inheritDoc}
     */
    public function __invoke(AppMeta $meta): void
    {
        throw new UnsafeCompileDir("Rejected by the application guard: {$meta->compileDir}");
    }
}

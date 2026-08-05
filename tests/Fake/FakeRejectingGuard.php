<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext\Fake;

use NaokiTsuchiya\RayDiContext\AppMeta;
use NaokiTsuchiya\RayDiContext\BakedPathGuardInterface;
use NaokiTsuchiya\RayDiContext\CompileDirGuardInterface;
use NaokiTsuchiya\RayDiContext\Exception\UnsafeCompileDir;

/** Application-supplied guard that rejects every compile dir, standing in for either guard interface */
final class FakeRejectingGuard implements CompileDirGuardInterface, BakedPathGuardInterface
{
    /** {@inheritDoc} */
    public function __invoke(AppMeta $meta): void
    {
        throw new UnsafeCompileDir("Rejected by the application guard: {$meta->compileDir}");
    }
}

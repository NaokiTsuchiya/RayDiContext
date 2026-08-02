<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext\Fake;

use NaokiTsuchiya\RayDiContext\AppMeta;
use NaokiTsuchiya\RayDiContext\BakedPathGuardInterface;

/** Application-supplied guard that records whether it was invoked */
final class FakeRecordingBakedPathGuard implements BakedPathGuardInterface
{
    /** Whether __invoke() was reached at all */
    public bool $called = false;

    /** {@inheritDoc} */
    public function __invoke(AppMeta $meta): void
    {
        $this->called = true;
    }
}

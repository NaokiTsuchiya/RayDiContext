<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext\Fake;

use NaokiTsuchiya\RayDiContext\AbstractContext;
use Ray\Di\AbstractModule;
use RuntimeException;

/** Fake context whose module resolution fails before the compiler ever runs */
final class FakeThrowingContext extends AbstractContext
{
    /** @throws RuntimeException Always. */
    public function __invoke(): AbstractModule
    {
        throw new RuntimeException('Fake context blew up');
    }
}

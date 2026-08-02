<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext\Fake;

use Ray\Di\AbstractModule;

/**
 * Fake module whose qualifier holds a "/" and produces a directory named "*.php"
 */
final class FakeQualifiedModule extends AbstractModule
{
    /** {@inheritDoc} */
    protected function configure(): void
    {
        $this->bind(FakeCarInterface::class)->annotatedWith('a.php/b')->to(FakeCar::class);
    }
}

<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext\Fake;

use Ray\Di\AbstractModule;

/**
 * Fake module whose qualifier holds a "/"
 *
 * Ray.Compiler names a script after its dependency index with only the namespace
 * separators replaced, so this binding is written to "…FakeCarInterface-a/b.php" —
 * a real subdirectory under the compile dir.
 */
final class FakeQualifiedModule extends AbstractModule
{
    /** {@inheritDoc} */
    protected function configure(): void
    {
        $this->bind(FakeCarInterface::class)->annotatedWith('a/b')->to(FakeCar::class);
    }
}

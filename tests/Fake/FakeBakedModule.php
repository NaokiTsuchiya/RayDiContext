<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext\Fake;

use NaokiTsuchiya\RayDiContext\AppMeta;
use Ray\Di\AbstractModule;

/**
 * Fake module reproducing the baked-path trap
 *
 * Binding the meta with toInstance() freezes every path it holds into the
 * compiled scripts, which is exactly what BakedPathGuard must detect.
 */
final class FakeBakedModule extends AbstractModule
{
    /**
     * @param AppMeta $meta Meta whose paths get baked by toInstance()
     */
    public function __construct(
        private readonly AppMeta $meta,
    ) {
        parent::__construct();
    }

    /**
     * {@inheritDoc}
     */
    protected function configure(): void
    {
        $this->bind(FakeCarInterface::class)->to(FakeCar::class);
        $this->bind(AppMeta::class)->toInstance($this->meta);
    }
}

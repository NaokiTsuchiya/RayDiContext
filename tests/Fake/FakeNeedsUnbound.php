<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext\Fake;

/** Fake dependency requiring the deliberately-unbound interface */
final class FakeNeedsUnbound
{
    /** @param FakeUnboundInterface $dependency Never bound, so compiling this fails */
    public function __construct(
        private readonly FakeUnboundInterface $dependency,
    ) {}

    /** Never called: compiling this class fails before anything can instantiate it */
    public function dependency(): FakeUnboundInterface
    {
        return $this->dependency;
    }
}

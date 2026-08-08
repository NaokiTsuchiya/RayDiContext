<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext\Fake;

use NaokiTsuchiya\RayDiContext\AppMeta;
use NaokiTsuchiya\RayDiContext\ContextInterface;
use Ray\Di\AbstractModule;

/** Fake context taking a constructor dependency beyond AppMeta, so it cannot extend AbstractContext */
final class FakeConfiguredContext implements ContextInterface
{
    /**
     * @param AppMeta $meta      Application metadata
     * @param string  $secretKey A dependency only the application can supply
     */
    public function __construct(
        public readonly AppMeta $meta,
        public readonly string $secretKey,
    ) {}

    /** {@inheritDoc} */
    public function __invoke(): AbstractModule
    {
        return new FakeModule();
    }
}

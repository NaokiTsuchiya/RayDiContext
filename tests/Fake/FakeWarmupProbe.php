<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext\Fake;

/** Fake singleton counting how many times a compiled script constructed it */
final class FakeWarmupProbe
{
    /** Constructions since the counter was last reset */
    private static int $constructed = 0;

    /** Records this construction */
    public function __construct()
    {
        self::$constructed++;
    }

    /** Forgets the constructions a previous test caused */
    public static function reset(): void
    {
        self::$constructed = 0;
    }

    /** Returns how many times a compiled script constructed this */
    public static function constructed(): int
    {
        return self::$constructed;
    }
}

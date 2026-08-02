<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\ExceptionInterface;
use NaokiTsuchiya\RayDiContext\Exception\InvalidExtraNeedle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * BakedPathGuard's constructor validation of $extraNeedles, kept apart from
 * BakedPathGuardExtraNeedleTest so neither class needs a filesystem fixture
 */
#[CoversClass(BakedPathGuard::class)]
#[CoversClass(InvalidExtraNeedle::class)]
final class BakedPathGuardInvalidNeedleTest extends TestCase
{
    /** Stands in for whatever an application knows must not reach a shipped script */
    private const CONFIGURED = 'zqx-must-not-ship-4f1c';

    /** @throws ExceptionInterface */
    #[Test]
    public function rejectsAnEmptyNeedleAtConstruction(): void
    {
        $this->expectException(InvalidExtraNeedle::class);

        new BakedPathGuard(['']);
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function rejectsAnEmptyNeedleAmongOthers(): void
    {
        $this->expectException(InvalidExtraNeedle::class);

        new BakedPathGuard([self::CONFIGURED, '']);
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function rejectsANonStringNeedle(): void
    {
        /** @var string $needle */
        $needle = 42;

        $this->expectException(InvalidExtraNeedle::class);

        new BakedPathGuard([$needle]);
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function invalidNeedleRejectionDoesNotEchoOtherNeedles(): void
    {
        try {
            new BakedPathGuard([self::CONFIGURED, '']);
            static::fail('InvalidExtraNeedle was not thrown');
        } catch (InvalidExtraNeedle $e) {
            static::assertStringNotContainsString(self::CONFIGURED, $e->getMessage());
        }
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function constructsWithNoExtraNeedles(): void
    {
        $this->expectNotToPerformAssertions();

        new BakedPathGuard();
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function constructsWithOnlyNonEmptyNeedles(): void
    {
        $this->expectNotToPerformAssertions();

        new BakedPathGuard([self::CONFIGURED, 'another-needle']);
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function acceptsAZeroStringNeedle(): void
    {
        $this->expectNotToPerformAssertions();

        new BakedPathGuard(['0']);
    }
}

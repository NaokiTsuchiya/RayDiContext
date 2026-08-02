<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\EmptyExtraNeedle;
use NaokiTsuchiya\RayDiContext\Exception\ExceptionInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * BakedPathGuard's constructor validation of $extraNeedles, kept apart from
 * BakedPathGuardExtraNeedleTest so neither class needs a filesystem fixture
 */
#[CoversClass(BakedPathGuard::class)]
#[CoversClass(EmptyExtraNeedle::class)]
final class BakedPathGuardEmptyNeedleTest extends TestCase
{
    /** Stands in for whatever an application knows must not reach a shipped script */
    private const CONFIGURED = 'zqx-must-not-ship-4f1c';

    /** @throws ExceptionInterface */
    #[Test]
    public function rejectsAnEmptyNeedleAtConstruction(): void
    {
        $this->expectException(EmptyExtraNeedle::class);

        new BakedPathGuard(['']);
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function rejectsAnEmptyNeedleAmongOthers(): void
    {
        $this->expectException(EmptyExtraNeedle::class);

        new BakedPathGuard([self::CONFIGURED, '']);
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function emptyNeedleRejectionDoesNotEchoOtherNeedles(): void
    {
        try {
            new BakedPathGuard([self::CONFIGURED, '']);
            static::fail('EmptyExtraNeedle was not thrown');
        } catch (EmptyExtraNeedle $e) {
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

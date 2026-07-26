<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\AbstractRuntimeException;
use NaokiTsuchiya\RayDiContext\Exception\ExceptionInterface;
use NaokiTsuchiya\RayDiContext\Fake\ExceptionClasses;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use RuntimeException;

use function is_a;

/**
 * ExceptionInterface says it marks every exception of this package; this holds it to that
 *
 * One case per exception class, so a violation names the class that broke instead of
 * failing a single aggregate assertion.
 */
#[CoversClass(AbstractRuntimeException::class)]
final class ExceptionHierarchyTest extends TestCase
{
    /**
     * The discovery is worth asserting on its own: an empty list would pass every case below
     *
     * @throws ReflectionException
     */
    #[Test]
    public function discoversTheExceptionClasses(): void
    {
        static::assertNotSame([], ExceptionClasses::provide());
    }

    /** @param class-string $class */
    #[Test]
    #[DataProviderExternal(ExceptionClasses::class, 'provide')]
    public function isCatchableAsTheMarker(string $class): void
    {
        static::assertTrue(is_a($class, ExceptionInterface::class, allow_string: true));
    }

    /** @param class-string $class */
    #[Test]
    #[DataProviderExternal(ExceptionClasses::class, 'provide')]
    public function isCatchableAsRuntimeException(string $class): void
    {
        static::assertTrue(is_a($class, RuntimeException::class, allow_string: true));
    }

    /**
     * @param class-string $class
     *
     * @throws ReflectionException
     */
    #[Test]
    #[DataProviderExternal(ExceptionClasses::class, 'provide')]
    public function isFinal(string $class): void
    {
        static::assertTrue((new ReflectionClass($class))->isFinal());
    }
}

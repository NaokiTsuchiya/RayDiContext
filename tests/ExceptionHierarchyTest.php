<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\AbstractRuntimeException;
use NaokiTsuchiya\RayDiContext\Exception\ExceptionInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use RuntimeException;

use function basename;
use function glob;
use function is_a;
use function sprintf;

/**
 * ExceptionInterface says it marks every exception of this package; this holds it to that
 *
 * The list is discovered from the source directory rather than hard-coded, so an exception
 * added later is covered without anyone remembering to register it here.
 */
#[CoversClass(AbstractRuntimeException::class)]
final class ExceptionHierarchyTest extends TestCase
{
    /**
     * Every concrete exception is catchable as ExceptionInterface, and as RuntimeException
     *
     * Every violation is collected before asserting, so one missing marker does not hide
     * the rest.
     *
     * @throws ReflectionException
     */
    #[Test]
    public function everyConcreteExceptionImplementsTheMarker(): void
    {
        $classes = $this->concreteExceptions();
        $violations = [];
        foreach ($classes as $class) {
            $implementsMarker = is_a($class, ExceptionInterface::class, allow_string: true);
            $extendsRuntime = is_a($class, RuntimeException::class, allow_string: true);
            $isFinal = (new ReflectionClass($class))->isFinal();
            if (!$implementsMarker) {
                $violations[] = sprintf('%s does not implement %s', $class, ExceptionInterface::class);
            }

            if (!$extendsRuntime) {
                $violations[] = sprintf('%s does not extend %s', $class, RuntimeException::class);
            }

            if (!$isFinal) {
                $violations[] = sprintf('%s is not final', $class);
            }
        }

        static::assertNotSame([], $classes, 'No exception classes were discovered');
        static::assertSame([], $violations);
    }

    /**
     * Returns every class under src/Exception except the marker and the base class
     *
     * @return list<class-string>
     *
     * @throws ReflectionException
     */
    private function concreteExceptions(): array
    {
        $namespace = (new ReflectionClass(ExceptionInterface::class))->getNamespaceName();
        $found = glob(__DIR__ . '/../src/Exception/*.php');
        $files = $found === false ? [] : $found;
        $classes = [];
        foreach ($files as $file) {
            $class = $namespace . '\\' . basename($file, suffix: '.php');
            if ($class === ExceptionInterface::class || $class === AbstractRuntimeException::class) {
                continue;
            }

            $classes[] = $class;
        }

        return $classes;
    }
}

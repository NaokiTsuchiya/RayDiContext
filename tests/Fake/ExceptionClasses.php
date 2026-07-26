<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext\Fake;

use NaokiTsuchiya\RayDiContext\Exception\AbstractRuntimeException;
use NaokiTsuchiya\RayDiContext\Exception\ExceptionInterface;
use ReflectionClass;
use ReflectionException;

use function array_chunk;
use function array_combine;
use function array_filter;
use function array_map;
use function array_values;
use function basename;
use function class_exists;
use function glob;

/**
 * Package exception discovery helper
 */
final class ExceptionClasses
{
    /**
     * Returns every concrete exception under src/Exception, keyed by class name
     *
     * The list is discovered from the source directory rather than hard-coded, so an
     * exception added later is covered without anyone remembering to register it.
     *
     * @return array<string, list<class-string>>
     *
     * @throws ReflectionException
     */
    public static function provide(): array
    {
        $namespace = (new ReflectionClass(ExceptionInterface::class))->getNamespaceName();
        $found = glob(__DIR__ . '/../../src/Exception/*.php');
        $files = $found === false ? [] : $found;
        $classes = array_values(array_filter(
            array_map(static fn(string $file): string => $namespace . '\\' . basename($file, suffix: '.php'), $files),
            static fn(string $class): bool => (
                class_exists($class)
                && $class !== ExceptionInterface::class
                && $class !== AbstractRuntimeException::class
            ),
        ));

        return array_combine($classes, array_chunk($classes, length: 1));
    }
}

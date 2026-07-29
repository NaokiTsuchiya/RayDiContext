<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\ContextClassNotFound;
use NaokiTsuchiya\RayDiContext\Exception\InvalidContextClass;
use NaokiTsuchiya\RayDiContext\Exception\UnknownContext;
use ReflectionClass;

use function array_keys;
use function class_exists;
use function implode;
use function interface_exists;
use function sprintf;

/**
 * Context provider backed by a context-name-to-class map
 *
 * @api
 */
final class MapContextProvider implements ContextProviderInterface
{
    /** @param array<string, class-string<AbstractContext>> $map context name to context class */
    public function __construct(
        private readonly array $map,
    ) {}

    /**
     * {@inheritDoc}
     *
     * @throws UnknownContext When no context class is mapped to $meta->context.
     * @throws ContextClassNotFound When the mapped context class does not exist.
     * @throws InvalidContextClass When the mapped class exists but cannot serve as a context.
     */
    public function get(AppMeta $meta): ContextInterface
    {
        $class = $this->map[$meta->context] ?? null;
        if ($class === null) {
            throw new UnknownContext(sprintf(
                'Unknown context "%s": known contexts are [%s]',
                $meta->context,
                implode(', ', array_keys($this->map)),
            ));
        }

        if (!class_exists($class) && !interface_exists($class)) {
            throw new ContextClassNotFound(sprintf(
                'Context class "%s" mapped to context "%s" does not exist',
                $class,
                $meta->context,
            ));
        }

        $reflection = new ReflectionClass($class);
        if ($reflection->isInterface()) {
            throw new InvalidContextClass(sprintf(
                'Context class "%s" mapped to context "%s" is an interface, not a class',
                $class,
                $meta->context,
            ));
        }

        if ($reflection->isAbstract()) {
            throw new InvalidContextClass(sprintf(
                'Context class "%s" mapped to context "%s" is abstract and cannot be instantiated',
                $class,
                $meta->context,
            ));
        }

        if (!$reflection->isSubclassOf(AbstractContext::class)) {
            throw new InvalidContextClass(sprintf(
                'Context class "%s" mapped to context "%s" must extend %s',
                $class,
                $meta->context,
                AbstractContext::class,
            ));
        }

        return new $class($meta);
    }
}

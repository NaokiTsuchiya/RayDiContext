<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\ContextClassNotFound;
use NaokiTsuchiya\RayDiContext\Exception\InvalidContextClass;
use NaokiTsuchiya\RayDiContext\Exception\UnknownContext;
use ReflectionClass;
use ReflectionException;

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
    /**
     * @param array<string, class-string<AbstractContext>> $map context name to context class
     *
     * Every entry is checked here, not lazily in get(): a typo in an entry for a
     * context nobody has requested yet would otherwise surface only once that
     * context is finally looked up, possibly long after the map was wired up.
     *
     * @throws ContextClassNotFound When a mapped context class does not exist.
     * @throws InvalidContextClass When a mapped class exists but cannot serve as a context.
     */
    public function __construct(
        private readonly array $map,
    ) {
        foreach ($this->map as $context => $class) {
            self::assertUsableContextClass($class, $context);
        }
    }

    /**
     * {@inheritDoc}
     *
     * @throws UnknownContext When no context class is mapped to $meta->context.
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

        // $class was already verified usable by the constructor.
        return new $class($meta);
    }

    /**
     * @throws ContextClassNotFound When $class does not exist.
     * @throws InvalidContextClass When $class exists but cannot serve as a context.
     */
    private static function assertUsableContextClass(string $class, string $context): void
    {
        if (!class_exists($class) && !interface_exists($class)) {
            throw new ContextClassNotFound(sprintf(
                'Context class "%s" mapped to context "%s" does not exist',
                $class,
                $context,
            ));
        }

        try {
            $reflection = new ReflectionClass($class);

            // @codeCoverageIgnoreStart
        } catch (ReflectionException $e) {
            // Unreachable from a test: class_exists()/interface_exists() above already
            // confirmed $class resolves, so ReflectionClass's constructor cannot fail here.
            // Caught anyway so this method's contract stays limited to this package's own
            // exceptions, with nothing left to declare.
            throw new ContextClassNotFound(
                sprintf('Context class "%s" mapped to context "%s" does not exist', $class, $context),
                previous: $e,
            );
        }
        // @codeCoverageIgnoreEnd

        if ($reflection->isInterface()) {
            throw new InvalidContextClass(sprintf(
                'Context class "%s" mapped to context "%s" is an interface, not a class',
                $class,
                $context,
            ));
        }

        if ($reflection->isAbstract()) {
            throw new InvalidContextClass(sprintf(
                'Context class "%s" mapped to context "%s" is abstract and cannot be instantiated',
                $class,
                $context,
            ));
        }

        if (!$reflection->isSubclassOf(AbstractContext::class)) {
            throw new InvalidContextClass(sprintf(
                'Context class "%s" mapped to context "%s" must extend %s',
                $class,
                $context,
                AbstractContext::class,
            ));
        }
    }
}

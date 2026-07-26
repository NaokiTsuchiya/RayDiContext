<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\ContextClassNotFound;
use NaokiTsuchiya\RayDiContext\Exception\UnknownContext;

use function array_keys;
use function class_exists;
use function implode;
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

        $exists = class_exists($class);
        if (!$exists) {
            throw new ContextClassNotFound(sprintf(
                'Context class "%s" mapped to context "%s" does not exist',
                $class,
                $meta->context,
            ));
        }

        return new $class($meta);
    }
}

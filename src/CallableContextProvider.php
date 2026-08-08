<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\InvalidContextFactory;
use NaokiTsuchiya\RayDiContext\Exception\UnknownContext;

use function array_keys;
use function get_debug_type;
use function implode;
use function is_callable;
use function sprintf;

/**
 * Context name to factory mapping, for contexts whose constructors take more than AppMeta
 *
 * The trade against MapContextProvider is validation depth: a class-string map proves at
 * construction that every mapped context is instantiable, where a factory can only be
 * proven callable — what it builds is checked when its context is first requested, because
 * invoking every factory up front would run constructors whose dependencies (a secrets
 * loader, a connection) may have side effects outside the requested environment.
 *
 * @api
 */
final class CallableContextProvider implements ContextProviderInterface
{
    /** @var array<string, callable(AppMeta): mixed> */
    private readonly array $map;

    /**
     * @param array<string, mixed> $map context name to a factory taking the AppMeta
     *
     * @throws InvalidContextFactory When a mapped value is not callable.
     */
    public function __construct(array $map)
    {
        /** @var mixed $factory */
        foreach ($map as $context => $factory) {
            if (!is_callable($factory)) {
                throw new InvalidContextFactory(sprintf(
                    'Context factory mapped to context "%s" is not callable',
                    $context,
                ));
            }
        }

        /** @var array<string, callable(AppMeta): mixed> $map */
        $this->map = $map;
    }

    /**
     * {@inheritDoc}
     *
     * @throws UnknownContext When no factory is mapped to $meta->context.
     * @throws InvalidContextFactory When the mapped factory returns something that is not a
     *         ContextInterface.
     */
    public function get(AppMeta $meta): ContextInterface
    {
        $factory = $this->map[$meta->context] ?? null;
        if ($factory === null) {
            throw new UnknownContext(sprintf(
                'Unknown context "%s": known contexts are [%s]',
                $meta->context,
                implode(', ', array_keys($this->map)),
            ));
        }

        /** @var mixed $context */
        $context = $factory($meta);
        if (!$context instanceof ContextInterface) {
            throw new InvalidContextFactory(sprintf(
                'Context factory mapped to context "%s" returned %s, not a %s',
                $meta->context,
                get_debug_type($context),
                ContextInterface::class,
            ));
        }

        return $context;
    }
}

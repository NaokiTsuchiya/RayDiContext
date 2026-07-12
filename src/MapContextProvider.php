<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\UnknownEnv;

use function array_keys;
use function implode;
use function sprintf;

/**
 * Context provider backed by an env-to-class map
 *
 * @api
 */
final class MapContextProvider implements ContextProviderInterface
{
    /** @param array<string, class-string<AbstractContext>> $map env name to context class */
    public function __construct(
        private readonly array $map,
    ) {}

    /**
     * {@inheritDoc}
     *
     * @throws UnknownEnv When no context is mapped to the env.
     */
    public function get(string $env, AppMeta $meta): ContextInterface
    {
        $class = $this->map[$env] ?? null;
        if ($class === null) {
            throw new UnknownEnv(sprintf(
                'Unknown env "%s": known envs are [%s]',
                $env,
                implode(', ', array_keys($this->map)),
            ));
        }

        return new $class($meta);
    }
}

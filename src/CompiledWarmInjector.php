<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\CompileDirUnavailable;
use NaokiTsuchiya\RayDiContext\Exception\WarmupNotCompiled;
use Override;
use Ray\Compiler\CompiledInjector;
use Ray\Compiler\Exception\ScriptDirNotReadable;
use Ray\Compiler\Exception\SingletonsFileNotFound;
use Ray\Di\Name;

use function sprintf;

/**
 * The warmable injector over an ahead-of-time compiled dir
 *
 * Resolution is `ray/compiler`'s; this adds the warmup contract and keeps `ray/compiler`'s own
 * exception types out of a caller's `catch`. `getInstance(InjectorInterface::class)` returns the
 * `CompiledInjector` underneath rather than this decorator, so a binding that injects the
 * injector receives one that cannot be warmed — warm this instance at boot instead.
 *
 * @api
 */
final class CompiledWarmInjector implements WarmInjectorInterface
{
    /** The compiled injector every call delegates to */
    private readonly CompiledInjector $injector;

    /**
     * @param non-empty-string $compileDir Directory holding the compiled scripts
     *
     * @throws CompileDirUnavailable When $compileDir does not exist or is not readable. Wraps
     *         ray/compiler's own ScriptDirNotReadable, retrievable via getPrevious().
     */
    public function __construct(
        private readonly string $compileDir,
    ) {
        try {
            $this->injector = new CompiledInjector($compileDir);
        } catch (ScriptDirNotReadable $e) {
            throw new CompileDirUnavailable(
                sprintf('Compile dir does not exist or is not readable: "%s"', $compileDir),
                previous: $e,
            );
        }
    }

    /**
     * {@inheritDoc}
     *
     * @param ''|class-string<T> $interface
     * @param string             $name
     *
     * @return ($interface is '' ? mixed : T)
     *
     * @template T of object
     */
    #[Override]
    public function getInstance($interface, $name = Name::ANY)
    {
        return $this->injector->getInstance($interface, $name);
    }

    /**
     * {@inheritDoc}
     *
     * @throws WarmupNotCompiled When the compile dir was written by a compiler that records no
     *         singleton metadata. Wraps ray/compiler's own SingletonsFileNotFound, retrievable
     *         via getPrevious().
     */
    #[Override]
    public function warmup(): void
    {
        try {
            $this->injector->warmup();
        } catch (SingletonsFileNotFound $e) {
            throw new WarmupNotCompiled(
                sprintf('Compile dir holds no singleton metadata to warm up: "%s"', $this->compileDir),
                previous: $e,
            );
        }
    }
}

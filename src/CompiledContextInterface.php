<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

/**
 * Marks a context resolved from the ahead-of-time compiled scripts
 *
 * The marker is the whole contract: InjectorBuilder returns a read-only CompiledInjector
 * over $meta->compileDir for a context carrying it, and a runtime injector otherwise. A
 * dev context and a prod context can share every line except this implements clause.
 *
 * @api
 */
interface CompiledContextInterface extends ContextInterface {}

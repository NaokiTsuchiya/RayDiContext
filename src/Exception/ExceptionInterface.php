<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext\Exception;

use Throwable;

/**
 * Marker for all exceptions of this package
 *
 * Throwable is extended rather than left to the implementations: `catch (ExceptionInterface $e)`
 * is the documented way to handle everything this package throws, and without it that $e carries
 * no getMessage()/getPrevious() as far as the type system is concerned — the caller has to
 * re-narrow to Throwable to read the very message the catch was written for.
 *
 * @api
 */
interface ExceptionInterface extends Throwable {}

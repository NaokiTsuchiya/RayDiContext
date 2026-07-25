<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext\Exception;

use RuntimeException as SplRuntimeException;

/**
 * Base for every runtime exception of this package
 *
 * Extending this is what makes an exception catchable as ExceptionInterface: a
 * concrete exception declares what went wrong, never that it belongs here.
 *
 * @api
 */
abstract class AbstractRuntimeException extends SplRuntimeException implements ExceptionInterface {}

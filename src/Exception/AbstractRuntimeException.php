<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext\Exception;

use RuntimeException as SplRuntimeException;

/**
 * Base for every runtime exception of this package
 *
 * @api
 */
abstract class AbstractRuntimeException extends SplRuntimeException implements ExceptionInterface {}

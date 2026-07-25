<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext\Exception;

use RuntimeException;

/**
 * The compile dir points at a directory whose contents must never be removed
 *
 * @api
 */
final class UnsafeCompileDir extends RuntimeException implements ExceptionInterface {}

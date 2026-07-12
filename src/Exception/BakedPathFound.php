<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext\Exception;

use RuntimeException;

/**
 * A compiled script contains a path literal that must be resolved at runtime
 *
 * @api
 */
final class BakedPathFound extends RuntimeException implements ExceptionInterface {}

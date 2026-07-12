<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext\Exception;

use RuntimeException;

/**
 * No context is mapped to the given env
 *
 * @api
 */
final class UnknownEnv extends RuntimeException implements ExceptionInterface {}

<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext\Exception;

/**
 * The compile dir points at a directory whose contents must never be removed
 *
 * @api
 */
final class UnsafeCompileDir extends AbstractRuntimeException {}

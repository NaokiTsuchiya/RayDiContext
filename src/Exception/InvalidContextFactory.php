<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext\Exception;

/**
 * A mapped context factory is not callable, or returned something that is not a context
 *
 * @api
 */
final class InvalidContextFactory extends AbstractRuntimeException {}

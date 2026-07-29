<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext\Exception;

/**
 * The context class mapped to a context name exists but cannot serve as a context
 *
 * @api
 */
final class InvalidContextClass extends AbstractRuntimeException {}

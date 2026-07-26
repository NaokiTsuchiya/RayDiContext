<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext\Exception;

/**
 * No context class is mapped to the given context name
 *
 * @api
 */
final class UnknownContext extends AbstractRuntimeException {}

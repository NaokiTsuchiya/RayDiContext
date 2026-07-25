<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext\Exception;

/**
 * No context is mapped to the given env
 *
 * @api
 */
final class UnknownEnv extends AbstractRuntimeException {}

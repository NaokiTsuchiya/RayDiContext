<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext\Exception;

/**
 * An entry inside the compile dir could not be made readable
 *
 * @api
 */
final class ChmodFailed extends AbstractRuntimeException {}

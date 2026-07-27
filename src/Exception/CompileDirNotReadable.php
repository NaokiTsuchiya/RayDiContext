<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext\Exception;

/**
 * A directory inside the compile dir, or the compile dir itself, could not be listed
 *
 * @api
 */
final class CompileDirNotReadable extends AbstractRuntimeException {}

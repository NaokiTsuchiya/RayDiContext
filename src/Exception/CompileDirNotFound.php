<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext\Exception;

/**
 * The compile dir does not exist, or the path is not a directory
 *
 * @api
 */
final class CompileDirNotFound extends AbstractRuntimeException {}

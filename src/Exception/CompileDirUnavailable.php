<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext\Exception;

/**
 * The compile dir does not exist or is not readable when constructing the runtime injector
 *
 * @api
 */
final class CompileDirUnavailable extends AbstractRuntimeException {}

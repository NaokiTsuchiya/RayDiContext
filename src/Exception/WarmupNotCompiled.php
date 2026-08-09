<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext\Exception;

/**
 * The compile dir holds no singleton metadata, so a warmup cannot know what to instantiate
 *
 * @api
 */
final class WarmupNotCompiled extends AbstractRuntimeException {}

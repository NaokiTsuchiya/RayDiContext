<?php

declare(strict_types=1);

// Intentionally throws a non-package exception to exercise the CLI's Throwable path: a
// bootstrap runs application code, so it can fail in ways this package has no exception for.
throw new LogicException('bootstrap blew up');

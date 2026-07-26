<?php

declare(strict_types=1);

use NaokiTsuchiya\RayDiContext\Fake\FakeBakedContext;
use NaokiTsuchiya\RayDiContext\Fake\FakeProdContext;
use NaokiTsuchiya\RayDiContext\MapContextProvider;

// "baked" maps to a context that bakes runtime paths on purpose, so the CLI's
// BakedPathFound exit status can be exercised end to end.
return new MapContextProvider([
    'prod' => FakeProdContext::class,
    'baked' => FakeBakedContext::class,
]);

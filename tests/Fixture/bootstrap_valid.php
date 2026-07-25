<?php

declare(strict_types=1);

use NaokiTsuchiya\RayDiContext\Fake\FakeProdContext;
use NaokiTsuchiya\RayDiContext\MapContextProvider;

return new MapContextProvider(['prod' => FakeProdContext::class]);

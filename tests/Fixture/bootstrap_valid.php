<?php

declare(strict_types=1);

use NaokiTsuchiya\RayDiContext\Fake\FakeBakedContext;
use NaokiTsuchiya\RayDiContext\Fake\FakeProdContext;
use NaokiTsuchiya\RayDiContext\Fake\FakeUnboundContext;
use NaokiTsuchiya\RayDiContext\MapContextProvider;

return new MapContextProvider([
    'prod' => FakeProdContext::class,
    'baked' => FakeBakedContext::class,
    'unbound' => FakeUnboundContext::class,
]);

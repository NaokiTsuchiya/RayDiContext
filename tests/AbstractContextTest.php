<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\ExceptionInterface;
use NaokiTsuchiya\RayDiContext\Fake\FakeDevContext;
use NaokiTsuchiya\RayDiContext\Fake\FakeModule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AbstractContext::class)]
final class AbstractContextTest extends TestCase
{
    /** @throws ExceptionInterface */
    #[Test]
    public function holdsTheMetaForSubclasses(): void
    {
        $meta = new AppMeta('/app', 'dev', '/app/var/di/dev', '/app/var/tmp/dev');

        $context = new FakeDevContext($meta);

        static::assertSame($meta, $context->getMeta());
        static::assertInstanceOf(FakeModule::class, $context());
    }
}

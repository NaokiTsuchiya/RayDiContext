<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\UnknownEnv;
use NaokiTsuchiya\RayDiContext\Fake\FakeDevContext;
use NaokiTsuchiya\RayDiContext\Fake\FakeProdContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MapContextProvider::class)]
final class MapContextProviderTest extends TestCase
{
    /**
     * Returns the context mapped to the env, constructed with the given meta
     *
     * @throws UnknownEnv
     */
    #[Test]
    public function getReturnsMappedContext(): void
    {
        $meta = new AppMeta('fake', '/app', '/app/var/di/dev', '/app/var/tmp/dev');
        $provider = new MapContextProvider([
            'dev' => FakeDevContext::class,
            'prod' => FakeProdContext::class,
        ]);

        $context = $provider->get('dev', $meta);

        static::assertInstanceOf(FakeDevContext::class, $context);
        static::assertSame($meta, $context->getMeta());
    }

    /**
     * An unmapped env is rejected with the known envs listed
     *
     * @throws UnknownEnv
     */
    #[Test]
    public function getThrowsOnUnknownEnv(): void
    {
        $provider = new MapContextProvider(['dev' => FakeDevContext::class]);

        $this->expectException(UnknownEnv::class);
        $this->expectExceptionMessage('Unknown env "prod": known envs are [dev]');

        $provider->get('prod', new AppMeta('fake', '/app', '/app/var/di/prod', '/app/var/tmp/prod'));
    }
}

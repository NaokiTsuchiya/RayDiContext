<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\ContextClassNotFound;
use NaokiTsuchiya\RayDiContext\Exception\InvalidAppMeta;
use NaokiTsuchiya\RayDiContext\Exception\UnknownContext;
use NaokiTsuchiya\RayDiContext\Fake\FakeDevContext;
use NaokiTsuchiya\RayDiContext\Fake\FakeProdContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MapContextProvider::class)]
final class MapContextProviderTest extends TestCase
{
    /**
     * Returns the context mapped to $meta->context, constructed with the given meta
     *
     * @throws UnknownContext
     * @throws ContextClassNotFound
     * @throws InvalidAppMeta
     */
    #[Test]
    public function getReturnsMappedContext(): void
    {
        $meta = new AppMeta('/app', 'dev', '/app/var/di/dev', '/app/var/tmp/dev');
        $provider = new MapContextProvider([
            'dev' => FakeDevContext::class,
            'prod' => FakeProdContext::class,
        ]);

        $context = $provider->get($meta);

        static::assertInstanceOf(FakeDevContext::class, $context);
        static::assertSame($meta, $context->getMeta());
    }

    /**
     * An unmapped context is rejected with the known contexts listed
     *
     * @throws UnknownContext
     * @throws ContextClassNotFound
     * @throws InvalidAppMeta
     */
    #[Test]
    public function getThrowsOnUnknownContext(): void
    {
        $provider = new MapContextProvider(['dev' => FakeDevContext::class]);

        $this->expectException(UnknownContext::class);
        $this->expectExceptionMessage('Unknown context "prod": known contexts are [dev]');

        $provider->get(new AppMeta('/app', 'prod', '/app/var/di/prod', '/app/var/tmp/prod'));
    }

    /**
     * A mapped class that does not exist is reported naming both the class and the context
     *
     * A misspelled class name in a bootstrap file would otherwise reach `new $class()` and
     * surface as a bare Error mentioning neither the context nor this package.
     *
     * @throws UnknownContext
     * @throws ContextClassNotFound
     * @throws InvalidAppMeta
     */
    #[Test]
    public function getThrowsOnMissingContextClass(): void
    {
        /** @var array<string, class-string<AbstractContext>> $map A bootstrap typo, as the runtime sees it */
        $map = ['prod' => 'NoSuchContextClass'];
        $provider = new MapContextProvider($map);

        $this->expectException(ContextClassNotFound::class);
        $this->expectExceptionMessage('Context class "NoSuchContextClass" mapped to context "prod" does not exist');

        $provider->get(new AppMeta('/app', 'prod', '/app/var/di/prod', '/app/var/tmp/prod'));
    }
}

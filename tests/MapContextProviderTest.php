<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use Countable;
use NaokiTsuchiya\RayDiContext\Exception\ContextClassNotFound;
use NaokiTsuchiya\RayDiContext\Exception\ExceptionInterface;
use NaokiTsuchiya\RayDiContext\Exception\InvalidContextClass;
use NaokiTsuchiya\RayDiContext\Exception\UnknownContext;
use NaokiTsuchiya\RayDiContext\Fake\FakeDevContext;
use NaokiTsuchiya\RayDiContext\Fake\FakeProdContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(MapContextProvider::class)]
final class MapContextProviderTest extends TestCase
{
    /** @throws ExceptionInterface */
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

    /** @throws ExceptionInterface */
    #[Test]
    public function getThrowsOnUnknownContext(): void
    {
        $provider = new MapContextProvider(['dev' => FakeDevContext::class]);

        $this->expectException(UnknownContext::class);
        $this->expectExceptionMessage('Unknown context "prod": known contexts are [dev]');

        $provider->get(new AppMeta('/app', 'prod', '/app/var/di/prod', '/app/var/tmp/prod'));
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function constructorThrowsOnMissingContextClass(): void
    {
        /** @var array<string, class-string<AbstractContext>> $map A bootstrap typo, as the runtime sees it */
        $map = ['prod' => 'NoSuchContextClass'];

        try {
            new MapContextProvider($map);
            static::fail('ContextClassNotFound was not thrown');
        } catch (ContextClassNotFound $e) {
            static::assertSame(
                'Context class "NoSuchContextClass" mapped to context "prod" does not exist',
                $e->getMessage(),
            );
            static::assertNull($e->getPrevious());
        }
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function constructorThrowsOnClassNotExtendingAbstractContext(): void
    {
        /** @var array<string, class-string<AbstractContext>> $map A bootstrap mistake, as the runtime sees it */
        $map = ['prod' => stdClass::class];

        $this->expectException(InvalidContextClass::class);
        $this->expectExceptionMessage('Context class "stdClass" mapped to context "prod" must extend '
        . AbstractContext::class);

        new MapContextProvider($map);
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function constructorThrowsOnAbstractContextClass(): void
    {
        /** @var array<string, class-string<AbstractContext>> $map A bootstrap mistake, as the runtime sees it */
        $map = ['prod' => AbstractContext::class];

        $this->expectException(InvalidContextClass::class);
        $this->expectExceptionMessage(
            'Context class "'
            . AbstractContext::class
            . '" mapped to context "prod" is abstract and cannot be instantiated',
        );

        new MapContextProvider($map);
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function constructorThrowsOnInterfaceContextClass(): void
    {
        /** @var array<string, class-string<AbstractContext>> $map A bootstrap mistake, as the runtime sees it */
        $map = ['prod' => Countable::class];

        $this->expectException(InvalidContextClass::class);
        $this->expectExceptionMessage(
            'Context class "' . Countable::class . '" mapped to context "prod" is an interface, not a class',
        );

        new MapContextProvider($map);
    }
}

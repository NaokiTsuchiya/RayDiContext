<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use Countable;
use NaokiTsuchiya\RayDiContext\Exception\ContextClassNotFound;
use NaokiTsuchiya\RayDiContext\Exception\InvalidAppMeta;
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
    /**
     * Returns the context mapped to $meta->context, constructed with the given meta
     *
     * @throws UnknownContext
     * @throws ContextClassNotFound
     * @throws InvalidContextClass
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
     * @throws InvalidContextClass
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
     * @throws InvalidContextClass
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

    /**
     * A mapped class unrelated to AbstractContext is rejected instead of reaching new $class()
     *
     * Without this check, PHP's own TypeError ("Return value must be of type ContextInterface,
     * stdClass returned") would leak past this package's exception hierarchy.
     *
     * @throws UnknownContext
     * @throws ContextClassNotFound
     * @throws InvalidContextClass
     * @throws InvalidAppMeta
     */
    #[Test]
    public function getThrowsOnClassNotExtendingAbstractContext(): void
    {
        /** @var array<string, class-string<AbstractContext>> $map A bootstrap mistake, as the runtime sees it */
        $map = ['prod' => stdClass::class];
        $provider = new MapContextProvider($map);

        $this->expectException(InvalidContextClass::class);
        $this->expectExceptionMessage(
            'Context class "stdClass" mapped to context "prod" must extend ' . AbstractContext::class,
        );

        $provider->get(new AppMeta('/app', 'prod', '/app/var/di/prod', '/app/var/tmp/prod'));
    }

    /**
     * A mapped abstract class is rejected instead of reaching new $class()
     *
     * Without this check, PHP's own Error ("Cannot instantiate abstract class AbstractContext")
     * would leak past this package's exception hierarchy.
     *
     * @throws UnknownContext
     * @throws ContextClassNotFound
     * @throws InvalidContextClass
     * @throws InvalidAppMeta
     */
    #[Test]
    public function getThrowsOnAbstractContextClass(): void
    {
        /** @var array<string, class-string<AbstractContext>> $map A bootstrap mistake, as the runtime sees it */
        $map = ['prod' => AbstractContext::class];
        $provider = new MapContextProvider($map);

        $this->expectException(InvalidContextClass::class);
        $this->expectExceptionMessage(
            'Context class "' . AbstractContext::class . '" mapped to context "prod" is abstract and cannot be instantiated',
        );

        $provider->get(new AppMeta('/app', 'prod', '/app/var/di/prod', '/app/var/tmp/prod'));
    }

    /**
     * A mapped interface is reported as an interface rather than "does not exist"
     *
     * class_exists() alone returns false for an interface name, which previously made this
     * case indistinguishable from a genuine typo (ContextClassNotFound's "does not exist").
     *
     * @throws UnknownContext
     * @throws ContextClassNotFound
     * @throws InvalidContextClass
     * @throws InvalidAppMeta
     */
    #[Test]
    public function getThrowsOnInterfaceContextClass(): void
    {
        /** @var array<string, class-string<AbstractContext>> $map A bootstrap mistake, as the runtime sees it */
        $map = ['prod' => Countable::class];
        $provider = new MapContextProvider($map);

        $this->expectException(InvalidContextClass::class);
        $this->expectExceptionMessage(
            'Context class "' . Countable::class . '" mapped to context "prod" is an interface, not a class',
        );

        $provider->get(new AppMeta('/app', 'prod', '/app/var/di/prod', '/app/var/tmp/prod'));
    }
}

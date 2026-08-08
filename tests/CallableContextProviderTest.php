<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\ExceptionInterface;
use NaokiTsuchiya\RayDiContext\Exception\InvalidContextFactory;
use NaokiTsuchiya\RayDiContext\Exception\UnknownContext;
use NaokiTsuchiya\RayDiContext\Fake\FakeConfiguredContext;
use NaokiTsuchiya\RayDiContext\Fake\FakeDevContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CallableContextProvider::class)]
final class CallableContextProviderTest extends TestCase
{
    /** Meta with conventional paths under a fictional app dir */
    private AppMeta $meta;

    /** @throws ExceptionInterface */
    protected function setUp(): void
    {
        $this->meta = new AppMeta('/app', 'prod', '/app/var/di/prod', '/app/var/tmp/prod');
    }

    /**
     * The reason this provider exists: a factory can supply what AppMeta alone cannot
     *
     * @throws ExceptionInterface
     */
    #[Test]
    public function getBuildsTheContextThroughTheMappedFactoryWithItsOwnDependencies(): void
    {
        $provider = new CallableContextProvider([
            'prod' => static fn(AppMeta $meta): FakeConfiguredContext => new FakeConfiguredContext($meta, 's3cr3t'),
        ]);

        $context = $provider->get($this->meta);

        static::assertInstanceOf(FakeConfiguredContext::class, $context);
        static::assertSame($this->meta, $context->meta);
        static::assertSame('s3cr3t', $context->secretKey);
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function getThrowsOnAContextNothingIsMappedTo(): void
    {
        $provider = new CallableContextProvider([
            'dev' => static fn(AppMeta $meta): FakeDevContext => new FakeDevContext($meta),
        ]);

        try {
            $provider->get($this->meta);
            static::fail('UnknownContext was not thrown');
        } catch (UnknownContext $e) {
            static::assertStringContainsString('prod', $e->getMessage());
            static::assertStringContainsString('dev', $e->getMessage());
        }
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function constructorThrowsOnAValueThatIsNotCallable(): void
    {
        try {
            new CallableContextProvider(['prod' => FakeDevContext::class]);
            static::fail('InvalidContextFactory was not thrown');
        } catch (InvalidContextFactory $e) {
            static::assertStringContainsString('prod', $e->getMessage());
        }
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function getThrowsWhenTheFactoryReturnsSomethingThatIsNotAContext(): void
    {
        $provider = new CallableContextProvider([
            'prod' => static fn(AppMeta $_meta): string => 'not a context',
        ]);

        try {
            $provider->get($this->meta);
            static::fail('InvalidContextFactory was not thrown');
        } catch (InvalidContextFactory $e) {
            static::assertStringContainsString('string', $e->getMessage());
            static::assertStringContainsString(ContextInterface::class, $e->getMessage());
        }
    }
}

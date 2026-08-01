<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\ExceptionInterface;
use NaokiTsuchiya\RayDiContext\Fake\FakeCar;
use NaokiTsuchiya\RayDiContext\Fake\FakeCarInterface;
use NaokiTsuchiya\RayDiContext\Fake\FakeDevContext;
use NaokiTsuchiya\RayDiContext\Fake\FakeProdContext;
use NaokiTsuchiya\RayDiContext\Support\Fs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ray\Di\Injector;

use function mkdir;
use function uniqid;

#[CoversClass(AbstractContext::class)]
final class AbstractContextTest extends TestCase
{
    /** Per-test working directory */
    private string $baseDir;

    /** {@inheritDoc} */
    protected function setUp(): void
    {
        $this->baseDir = __DIR__ . '/tmp/' . uniqid('context_', more_entropy: true);
    }

    /** {@inheritDoc} */
    protected function tearDown(): void
    {
        Fs::removeDir($this->baseDir);
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function getSavedSingletonDefaultsToEmpty(): void
    {
        $context = new FakeDevContext(new AppMeta('/app', 'dev', '/app/var/di/dev', '/app/var/tmp/dev'));

        static::assertSame([], $context->getSavedSingleton());
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function getSavedSingletonOverride(): void
    {
        $context = new FakeProdContext(new AppMeta('/app', 'prod', '/app/var/di/prod', '/app/var/tmp/prod'));

        static::assertSame([FakeCarInterface::class], $context->getSavedSingleton());
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function devContextResolvesWithRuntimeInjector(): void
    {
        $tmpDir = "{$this->baseDir}/tmp";
        mkdir($tmpDir, permissions: 0o755, recursive: true);
        $context = new FakeDevContext(new AppMeta($this->baseDir, 'dev', "{$this->baseDir}/di", $tmpDir));

        $injector = $context->getInjectorInstance();

        static::assertInstanceOf(Injector::class, $injector);
        static::assertInstanceOf(FakeCar::class, $injector->getInstance(FakeCarInterface::class));
    }
}

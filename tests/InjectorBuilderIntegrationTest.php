<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use FilesystemIterator;
use NaokiTsuchiya\RayDiContext\Exception\ExceptionInterface;
use NaokiTsuchiya\RayDiContext\Fake\FakeCar;
use NaokiTsuchiya\RayDiContext\Fake\FakeCarInterface;
use NaokiTsuchiya\RayDiContext\Fake\FakeProdContext;
use NaokiTsuchiya\RayDiContext\Support\AppDirFixture;
use NaokiTsuchiya\RayDiContext\Support\Fs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SplFileInfo;

use function chmod;
use function dirname;
use function hash_file;
use function is_dir;
use function ksort;

/** The compiled-context path, which needs a real compile to observe */
#[CoversClass(InjectorBuilder::class)]
final class InjectorBuilderIntegrationTest extends TestCase
{
    /** Working directory and meta shared by the resolution tests */
    private AppDirFixture $fixture;

    /** Meta with conventional paths under the app dir */
    private AppMeta $meta;

    /** System under test */
    private InjectorBuilder $builder;

    /** @throws ExceptionInterface */
    protected function setUp(): void
    {
        $this->fixture = new AppDirFixture('builder_integration_');
        $this->meta = $this->fixture->meta;
        $this->builder = new InjectorBuilder();
        (new CompileRunner(new MapContextProvider(['prod' => FakeProdContext::class])))->run($this->meta);
    }

    /** {@inheritDoc} */
    protected function tearDown(): void
    {
        $exists = is_dir($this->meta->compileDir);
        if ($exists) {
            chmod($this->meta->compileDir, permissions: 0o755);
        }

        $this->fixture->remove();
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function buildsACompiledWarmableInjectorResolvingTheCompiledAppModule(): void
    {
        $injector = ($this->builder)(new FakeProdContext($this->meta), $this->meta);

        static::assertInstanceOf(CompiledWarmableInjector::class, $injector);
        static::assertInstanceOf(FakeCar::class, $injector->getInstance(FakeCarInterface::class));
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function resolvesFromReadOnlyCompileDir(): void
    {
        chmod($this->meta->compileDir, permissions: 0o555);
        $before = $this->snapshot($this->meta->compileDir);

        $injector = ($this->builder)(new FakeProdContext($this->meta), $this->meta);

        static::assertInstanceOf(CompiledWarmableInjector::class, $injector);
        static::assertInstanceOf(FakeCar::class, $injector->getInstance(FakeCarInterface::class));
        static::assertSame($before, $this->snapshot($this->meta->compileDir));
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function resolvesWithoutCompileTimeTmpDir(): void
    {
        Fs::removeDir(dirname($this->meta->tmpDir));
        $runtimeMeta = new AppMeta(
            $this->meta->appDir,
            'prod',
            $this->meta->compileDir,
            "{$this->fixture->baseDir}/absent-tmp",
        );

        $injector = ($this->builder)(new FakeProdContext($runtimeMeta), $runtimeMeta);

        static::assertInstanceOf(FakeCar::class, $injector->getInstance(FakeCarInterface::class));
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function resolvesAfterCompileDirMovesToADifferentAbsolutePath(): void
    {
        $relocatedCompileDir = "{$this->fixture->baseDir}/deployed/var/di/prod";
        Fs::copyDir($this->meta->compileDir, $relocatedCompileDir);
        Fs::removeDir($this->meta->compileDir);
        $relocatedMeta = new AppMeta($this->meta->appDir, 'prod', $relocatedCompileDir, $this->meta->tmpDir);

        $injector = ($this->builder)(new FakeProdContext($relocatedMeta), $relocatedMeta);

        static::assertInstanceOf(CompiledWarmableInjector::class, $injector);
        static::assertInstanceOf(FakeCar::class, $injector->getInstance(FakeCarInterface::class));
    }

    /** @return array<string, string> */
    private function snapshot(string $dir): array
    {
        $files = [];
        /** @var SplFileInfo $entry */
        foreach (new FilesystemIterator($dir) as $entry) {
            $pathname = $entry->getPathname();
            $hash = hash_file('sha256', $pathname);
            $files[$pathname] = $hash === false ? '' : $hash;
        }

        ksort($files);

        return $files;
    }
}

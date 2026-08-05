<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Support\CliFixture;
use NaokiTsuchiya\RayDiContext\Support\PhpProcess;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function glob;

/** End-to-end test for the bin/ray-di-compile CLI, run as a separate process */
#[CoversNothing]
final class BinCompileIntegrationTest extends TestCase
{
    /** Path to the compile CLI under test */
    private const SCRIPT = __DIR__ . '/../bin/ray-di-compile';

    /** Working directory and error stream */
    private CliFixture $fixture;

    /** {@inheritDoc} */
    protected function setUp(): void
    {
        $this->fixture = new CliFixture('bin_compile_');
    }

    /** {@inheritDoc} */
    protected function tearDown(): void
    {
        $this->fixture->remove();
    }

    /** @throws RuntimeException */
    #[Test]
    public function compilesMappedContext(): void
    {
        $appDir = $this->fixture->appDir;

        [$status, $stderr] = PhpProcess::run(self::SCRIPT, [
            CliFixture::VALID,
            $appDir,
            'prod',
        ]);

        static::assertSame(0, $status, $stderr);
        static::assertNotSame([], glob("{$appDir}/var/di/prod/*FakeCarInterface*.php"));
    }

    /** @throws RuntimeException */
    #[Test]
    public function compilesToExplicitOverride(): void
    {
        $appDir = $this->fixture->appDir;
        $compileDir = "{$this->fixture->baseDir}/custom-di";
        $tmpDir = "{$appDir}/var/tmp/prod";

        [$status, $stderr] = PhpProcess::run(self::SCRIPT, [
            CliFixture::VALID,
            $appDir,
            'prod',
            $compileDir,
            $tmpDir,
        ]);

        static::assertSame(0, $status, $stderr);
        static::assertNotSame([], glob("{$compileDir}/*FakeCarInterface*.php"));
        static::assertSame([], glob("{$appDir}/var/di/prod/*.php"));
    }

    /** @throws RuntimeException */
    #[Test]
    public function failsWithStatusOneOnBakedPath(): void
    {
        $appDir = $this->fixture->appDir;

        [$status, $stderr] = PhpProcess::run(self::SCRIPT, [
            CliFixture::VALID,
            $appDir,
            'baked',
        ]);

        static::assertSame(1, $status, $stderr);
        static::assertStringContainsString('Baked path', $stderr);
        static::assertStringContainsString($appDir, $stderr);
        static::assertStringNotContainsString('Stack trace', $stderr);
    }

    /** @throws RuntimeException */
    #[Test]
    public function failsWithStatusOneOnUnknownContext(): void
    {
        [$status, $stderr] = PhpProcess::run(self::SCRIPT, [
            CliFixture::VALID,
            $this->fixture->appDir,
            'nosuch',
        ]);

        static::assertSame(1, $status, $stderr);
        static::assertStringContainsString('Unknown context "nosuch"', $stderr);
        static::assertStringNotContainsString('Stack trace', $stderr);
    }

    /** @throws RuntimeException */
    #[Test]
    public function failsWithStatusOneOnRelativeAppDir(): void
    {
        [$status, $stderr] = PhpProcess::run(self::SCRIPT, [
            CliFixture::VALID,
            '.',
            'prod',
        ]);

        static::assertSame(1, $status, $stderr);
        static::assertStringContainsString('must be an absolute path', $stderr);
        static::assertStringNotContainsString('Stack trace', $stderr);
    }

    /** @throws RuntimeException */
    #[Test]
    public function failsWithStatusOneOnAnUnboundCompilerDependency(): void
    {
        [$status, $stderr] = PhpProcess::run(self::SCRIPT, [
            CliFixture::VALID,
            $this->fixture->appDir,
            'unbound',
        ]);

        static::assertSame(1, $status, $stderr);
        static::assertStringContainsString('Unbound', $stderr);
        static::assertStringNotContainsString('Stack trace', $stderr);
    }

    /** @throws RuntimeException */
    #[Test]
    public function failsWithUsageWhenArgumentsMissing(): void
    {
        [$status, $stderr] = PhpProcess::run(self::SCRIPT, []);

        static::assertSame(2, $status);
        static::assertStringContainsString('Usage:', $stderr);
    }

    /** @throws RuntimeException */
    #[Test]
    public function failsWhenBootstrapReturnsWrongType(): void
    {
        [$status, $stderr] = PhpProcess::run(self::SCRIPT, [
            CliFixture::INVALID,
            $this->fixture->appDir,
            'prod',
        ]);

        static::assertSame(2, $status);
        static::assertStringContainsString('must return', $stderr);
    }

    /** @throws RuntimeException */
    #[Test]
    public function failsWithUsageWhenAppDirDoesNotExist(): void
    {
        $appDir = "{$this->fixture->baseDir}/nosuch";

        [$status, $stderr] = PhpProcess::run(self::SCRIPT, [
            CliFixture::VALID,
            $appDir,
            'prod',
        ]);

        static::assertSame(2, $status, $stderr);
        static::assertStringContainsString('appDir does not exist', $stderr);
        static::assertStringContainsString($appDir, $stderr);
        static::assertStringNotContainsString('Stack trace', $stderr);
    }

    /** @throws RuntimeException */
    #[Test]
    public function failsWithUsageWhenTooManyArguments(): void
    {
        $appDir = $this->fixture->appDir;

        [$status, $stderr] = PhpProcess::run(self::SCRIPT, [
            CliFixture::VALID,
            $appDir,
            'prod',
            '',
            '',
            'surplus',
        ]);

        static::assertSame(2, $status);
        static::assertStringContainsString('Too many arguments', $stderr);
        static::assertStringContainsString('Usage:', $stderr);
        static::assertSame([], glob("{$appDir}/var/di/prod/*.php"));
    }
}

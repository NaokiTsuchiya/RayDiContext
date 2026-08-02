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

/** Arguments bin/ray-di-compile refuses before compiling anything, all of them exit status 2 */
#[CoversNothing]
final class BinCompileRejectionTest extends TestCase
{
    /** Path to the compile CLI under test */
    private const SCRIPT = __DIR__ . '/../bin/ray-di-compile';

    /** Working directory and error stream */
    private CliFixture $fixture;

    /** {@inheritDoc} */
    protected function setUp(): void
    {
        $this->fixture = new CliFixture();
    }

    /** {@inheritDoc} */
    protected function tearDown(): void
    {
        $this->fixture->remove();
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

<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Support\Fs;
use NaokiTsuchiya\RayDiContext\Support\PhpProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function glob;
use function mkdir;
use function uniqid;

/** Arguments bin/ray-di-compile refuses before compiling anything, all of them exit status 2 */
final class BinCompileRejectionTest extends TestCase
{
    /** Path to the compile CLI under test */
    private const SCRIPT = __DIR__ . '/../bin/ray-di-compile';

    /** Directory holding the prepared bootstrap stub files */
    private const FIXTURE_DIR = __DIR__ . '/Fixture';

    /** Per-test working directory */
    private string $baseDir;

    /** {@inheritDoc} */
    protected function setUp(): void
    {
        $this->baseDir = __DIR__ . '/tmp/' . uniqid('bin_reject_', more_entropy: true);
        mkdir("{$this->baseDir}/app/var/tmp/prod", permissions: 0o755, recursive: true);
    }

    /** {@inheritDoc} */
    protected function tearDown(): void
    {
        Fs::removeDir($this->baseDir);
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
            self::FIXTURE_DIR . '/bootstrap_invalid.php',
            "{$this->baseDir}/app",
            'prod',
        ]);

        static::assertSame(2, $status);
        static::assertStringContainsString('must return', $stderr);
    }

    /** @throws RuntimeException */
    #[Test]
    public function failsWithUsageWhenAppDirDoesNotExist(): void
    {
        $appDir = "{$this->baseDir}/nosuch";

        [$status, $stderr] = PhpProcess::run(self::SCRIPT, [
            self::FIXTURE_DIR . '/bootstrap_valid.php',
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
        $appDir = "{$this->baseDir}/app";

        [$status, $stderr] = PhpProcess::run(self::SCRIPT, [
            self::FIXTURE_DIR . '/bootstrap_valid.php',
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

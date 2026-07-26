<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Fake\Cli;
use NaokiTsuchiya\RayDiContext\Fake\Fs;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function glob;
use function mkdir;
use function uniqid;

/**
 * End-to-end test for the bin/ray-di-compile CLI
 */
final class BinCompileTest extends TestCase
{
    /** Path to the compile CLI under test */
    private const SCRIPT = __DIR__ . '/../bin/ray-di-compile';

    /** Directory holding the prepared bootstrap stub files */
    private const FIXTURE_DIR = __DIR__ . '/Fixture';

    /** Per-test working directory */
    private string $baseDir;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->baseDir = __DIR__ . '/tmp/' . uniqid('bin_', more_entropy: true);
        mkdir("{$this->baseDir}/app/var/tmp/prod", permissions: 0o755, recursive: true);
    }

    /**
     * {@inheritDoc}
     */
    protected function tearDown(): void
    {
        Fs::removeDir($this->baseDir);
    }

    /**
     * The CLI compiles the mapped context and exits with status 0
     *
     * @throws RuntimeException
     */
    #[Test]
    public function compilesMappedContext(): void
    {
        $appDir = "{$this->baseDir}/app";

        [$status, $stderr] = Cli::run(self::SCRIPT, [
            self::FIXTURE_DIR . '/bootstrap_valid.php',
            $appDir,
            'prod',
        ]);

        static::assertSame(0, $status, $stderr);
        static::assertNotSame([], glob("{$appDir}/var/di/prod/*FakeCarInterface*.php"));
    }

    /**
     * Explicit compileDir/tmpDir CLI arguments override the conventional defaults
     *
     * @throws RuntimeException
     */
    #[Test]
    public function compilesToExplicitOverride(): void
    {
        $appDir = "{$this->baseDir}/app";
        $compileDir = "{$this->baseDir}/custom-di";
        $tmpDir = "{$this->baseDir}/app/var/tmp/prod";

        [$status, $stderr] = Cli::run(self::SCRIPT, [
            self::FIXTURE_DIR . '/bootstrap_valid.php',
            $appDir,
            'prod',
            $compileDir,
            $tmpDir,
        ]);

        static::assertSame(0, $status, $stderr);
        static::assertNotSame([], glob("{$compileDir}/*FakeCarInterface*.php"));
        static::assertSame([], glob("{$appDir}/var/di/prod/*.php"));
    }

    /**
     * The CLI reports a usage error when arguments are missing
     *
     * @throws RuntimeException
     */
    #[Test]
    public function failsWithUsageWhenArgumentsMissing(): void
    {
        [$status, $stderr] = Cli::run(self::SCRIPT, []);

        static::assertSame(2, $status);
        static::assertStringContainsString('Usage:', $stderr);
    }

    /**
     * The CLI rejects a bootstrap that does not return a provider
     *
     * @throws RuntimeException
     */
    #[Test]
    public function failsWhenBootstrapReturnsWrongType(): void
    {
        [$status, $stderr] = Cli::run(self::SCRIPT, [
            self::FIXTURE_DIR . '/bootstrap_invalid.php',
            "{$this->baseDir}/app",
            'prod',
        ]);

        static::assertSame(2, $status);
        static::assertStringContainsString('must return', $stderr);
    }
}

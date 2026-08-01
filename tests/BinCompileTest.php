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

/** End-to-end test for the bin/ray-di-compile CLI */
final class BinCompileTest extends TestCase
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
        $this->baseDir = __DIR__ . '/tmp/' . uniqid('bin_', more_entropy: true);
        mkdir("{$this->baseDir}/app/var/tmp/prod", permissions: 0o755, recursive: true);
    }

    /** {@inheritDoc} */
    protected function tearDown(): void
    {
        Fs::removeDir($this->baseDir);
    }

    /** @throws RuntimeException */
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

    /** @throws RuntimeException */
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

    /** @throws RuntimeException */
    #[Test]
    public function failsWithUsageWhenArgumentsMissing(): void
    {
        [$status, $stderr] = Cli::run(self::SCRIPT, []);

        static::assertSame(2, $status);
        static::assertStringContainsString('Usage:', $stderr);
    }

    /** @throws RuntimeException */
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

    /**
     * This is the CI guard the package exists for: the status has to be usable, and the
     * message has to be the first thing in the log rather than buried under a trace.
     *
     * @throws RuntimeException
     */
    #[Test]
    public function failsWithStatusOneOnBakedPath(): void
    {
        $appDir = "{$this->baseDir}/app";

        [$status, $stderr] = Cli::run(self::SCRIPT, [
            self::FIXTURE_DIR . '/bootstrap_valid.php',
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
        [$status, $stderr] = Cli::run(self::SCRIPT, [
            self::FIXTURE_DIR . '/bootstrap_valid.php',
            "{$this->baseDir}/app",
            'nosuch',
        ]);

        static::assertSame(1, $status, $stderr);
        static::assertStringContainsString('Unknown context "nosuch"', $stderr);
        static::assertStringNotContainsString('Stack trace', $stderr);
    }

    /**
     * The CLI checks existence before compiling so the message points at the argument;
     * shape is left to AppMeta::fromAppDir(), which never touches the filesystem.
     *
     * @throws RuntimeException
     */
    #[Test]
    public function distinguishesMissingFromRelativeAppDir(): void
    {
        $appDir = "{$this->baseDir}/nosuch";

        [$status, $stderr] = Cli::run(self::SCRIPT, [
            self::FIXTURE_DIR . '/bootstrap_valid.php',
            $appDir,
            'prod',
        ]);

        static::assertSame(2, $status, $stderr);
        static::assertStringContainsString('appDir does not exist', $stderr);
        static::assertStringContainsString($appDir, $stderr);
        static::assertStringNotContainsString('Stack trace', $stderr);

        [$status, $stderr] = Cli::run(self::SCRIPT, [
            self::FIXTURE_DIR . '/bootstrap_valid.php',
            '.',
            'prod',
        ]);

        static::assertSame(1, $status, $stderr);
        static::assertStringContainsString('must be an absolute path', $stderr);
        static::assertStringNotContainsString('Stack trace', $stderr);
    }

    /** @throws RuntimeException */
    #[Test]
    public function failsWithUsageWhenTooManyArguments(): void
    {
        $appDir = "{$this->baseDir}/app";

        [$status, $stderr] = Cli::run(self::SCRIPT, [
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

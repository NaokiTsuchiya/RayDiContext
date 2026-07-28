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

    /**
     * A baked path fails the compile with status 1 and a readable one-line message
     *
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

    /**
     * An unknown context fails with status 1, listing the contexts the bootstrap maps
     *
     * @throws RuntimeException
     */
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
     * An appDir that does not exist is a usage error naming the argument
     *
     * Checked before anything is compiled, so the message points at the argument rather
     * than at a baked path or a mkdir failure downstream. This is a CLI-level check, not
     * AppMeta's: AppMeta::fromAppDir() only validates the string's shape (absolute or
     * not), never touches the filesystem.
     *
     * @throws RuntimeException
     */
    #[Test]
    public function failsWithUsageOnMissingAppDir(): void
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
    }

    /**
     * A relative appDir fails the compile with status 1, not a usage error
     *
     * AppMeta::fromAppDir() rejects it as an InvalidAppMeta rather than resolving it
     * against the working directory, so it surfaces the same way a baked path or an
     * unknown context does.
     *
     * @throws RuntimeException
     */
    #[Test]
    public function failsWithStatusOneOnRelativeAppDir(): void
    {
        [$status, $stderr] = Cli::run(self::SCRIPT, [
            self::FIXTURE_DIR . '/bootstrap_valid.php',
            '.',
            'prod',
        ]);

        static::assertSame(1, $status, $stderr);
        static::assertStringContainsString('must be an absolute path', $stderr);
        static::assertStringNotContainsString('Stack trace', $stderr);
    }

    /**
     * Surplus arguments are a usage error, not a silently successful compile
     *
     * @throws RuntimeException
     */
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

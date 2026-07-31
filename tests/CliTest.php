<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Fake\Fs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function file_get_contents;
use function glob;
use function mkdir;
use function uniqid;

/**
 * What the CLI does once the arguments are usable; CliRejectionTest covers the ones that are not
 *
 * BinCompileTest drives the same contract through a real subprocess, which is what proves the
 * installed binary works; it cannot contribute coverage, since the compile happens in another
 * process. These cases cover the branches instead.
 */
#[CoversClass(Cli::class)]
final class CliTest extends TestCase
{
    /** Bootstrap mapping "prod" and "baked" to fake contexts */
    private const VALID = __DIR__ . '/Fixture/bootstrap_valid.php';

    /** @var non-empty-string Per-test working directory */
    private string $baseDir;

    /** @var non-empty-string App dir the compile writes below */
    private string $appDir;

    /** @var non-empty-string File standing in for STDERR */
    private string $errorFile;

    /** System under test */
    private Cli $cli;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->baseDir = __DIR__ . '/tmp/' . uniqid('cli_', more_entropy: true);
        $this->appDir = "{$this->baseDir}/app";
        mkdir("{$this->appDir}/var/tmp/prod", permissions: 0o755, recursive: true);
        $this->errorFile = "{$this->baseDir}/stderr.txt";
        $this->cli = new Cli($this->errorFile);
    }

    /**
     * {@inheritDoc}
     */
    protected function tearDown(): void
    {
        Fs::removeDir($this->baseDir);
    }

    /**
     * A mapped context compiles and reports success
     *
     * @throws RuntimeException
     */
    #[Test]
    public function compilesMappedContext(): void
    {
        $status = ($this->cli)(['bin', self::VALID, $this->appDir, 'prod']);

        static::assertSame(0, $status);
        static::assertNotSame([], glob("{$this->appDir}/var/di/prod/*FakeCarInterface*.php"));
    }

    /**
     * An empty override reads as "not given", so the conventional path is still used
     *
     * The documented invocation forwards "$APP_COMPILE_DIR" through the shell, so an unset
     * variable arrives as an empty argument rather than as no argument at all.
     *
     * @throws RuntimeException
     */
    #[Test]
    public function treatsEmptyOverrideAsAbsent(): void
    {
        $status = ($this->cli)(['bin', self::VALID, $this->appDir, 'prod', '', '']);

        static::assertSame(0, $status);
        static::assertNotSame([], glob("{$this->appDir}/var/di/prod/*FakeCarInterface*.php"));
    }

    /**
     * An explicit override compiles somewhere other than the conventional path
     *
     * @throws RuntimeException
     */
    #[Test]
    public function honoursExplicitOverrides(): void
    {
        $compileDir = "{$this->baseDir}/elsewhere/di";

        $status = ($this->cli)(['bin', self::VALID, $this->appDir, 'prod', $compileDir, "{$this->baseDir}/rw"]);

        static::assertSame(0, $status);
        static::assertNotSame([], glob("{$compileDir}/*FakeCarInterface*.php"));
        static::assertSame([], glob("{$this->appDir}/var/di/prod/*.php"));
    }

    /**
     * A package exception is reported as one line, without its class name or a trace
     *
     * @throws RuntimeException
     */
    #[Test]
    public function reportsPackageExceptionAsRuntimeFailure(): void
    {
        $status = ($this->cli)(['bin', self::VALID, $this->appDir, 'nosuch']);

        static::assertSame(1, $status);
        static::assertStringContainsString('Unknown context "nosuch"', $this->stderr());
        static::assertStringNotContainsString('Stack trace', $this->stderr());
    }

    /**
     * A failure that is not this package's own is reported as one line naming its class
     *
     * Requiring the bootstrap and compiling the module run application code and Ray.Di, so the
     * most ordinary compile failure — a missing binding — arrives as a foreign exception. Left
     * uncaught it escaped as a fatal with a stack trace and exit 255, outside the contract.
     *
     * @throws RuntimeException
     */
    #[Test]
    public function reportsForeignThrowableAsRuntimeFailure(): void
    {
        $status = ($this->cli)(['bin', __DIR__ . '/Fixture/bootstrap_throwing.php', $this->appDir, 'prod']);

        static::assertSame(1, $status);
        static::assertStringContainsString('LogicException: bootstrap blew up', $this->stderr());
        static::assertStringNotContainsString('Stack trace', $this->stderr());
    }

    /**
     * Returns what the CLI wrote to its error stream
     *
     * @throws RuntimeException
     */
    private function stderr(): string
    {
        $written = file_get_contents($this->errorFile);

        return $written === false ? '' : $written;
    }
}

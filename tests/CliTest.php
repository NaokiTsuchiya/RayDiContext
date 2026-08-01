<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Fake\CliFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function glob;

/**
 * What the CLI does once the arguments are usable; CliRejectionTest covers the ones that are not
 */
#[CoversClass(Cli::class)]
final class CliTest extends TestCase
{
    /** Working directory, error stream and Cli under test */
    private CliFixture $fixture;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->fixture = new CliFixture();
    }

    /**
     * {@inheritDoc}
     */
    protected function tearDown(): void
    {
        $this->fixture->remove();
    }

    /**
     * A mapped context compiles and reports success
     */
    #[Test]
    public function compilesMappedContext(): void
    {
        $status = ($this->fixture->cli)(['bin', CliFixture::VALID, $this->fixture->appDir, 'prod']);

        static::assertSame(0, $status);
        static::assertNotSame([], glob("{$this->fixture->appDir}/var/di/prod/*FakeCarInterface*.php"));
    }

    /**
     * An empty override reads as "not given", so the conventional path is still used
     *
     * The documented invocation forwards "$APP_COMPILE_DIR" through the shell, so an unset
     * variable arrives as an empty argument rather than as no argument at all.
     */
    #[Test]
    public function treatsEmptyOverrideAsAbsent(): void
    {
        $status = ($this->fixture->cli)(['bin', CliFixture::VALID, $this->fixture->appDir, 'prod', '', '']);

        static::assertSame(0, $status);
        static::assertNotSame([], glob("{$this->fixture->appDir}/var/di/prod/*FakeCarInterface*.php"));
    }

    /**
     * An explicit override compiles somewhere other than the conventional path
     */
    #[Test]
    public function honoursExplicitOverrides(): void
    {
        $compileDir = "{$this->fixture->baseDir}/elsewhere/di";

        $status = ($this->fixture->cli)([
            'bin',
            CliFixture::VALID,
            $this->fixture->appDir,
            'prod',
            $compileDir,
            "{$this->fixture->baseDir}/rw",
        ]);

        static::assertSame(0, $status);
        static::assertNotSame([], glob("{$compileDir}/*FakeCarInterface*.php"));
        static::assertSame([], glob("{$this->fixture->appDir}/var/di/prod/*.php"));
    }

    /**
     * A package exception is reported as one line, without its class name or a trace
     */
    #[Test]
    public function reportsPackageExceptionAsRuntimeFailure(): void
    {
        $status = ($this->fixture->cli)(['bin', CliFixture::VALID, $this->fixture->appDir, 'nosuch']);

        static::assertSame(1, $status);
        static::assertStringContainsString('Unknown context "nosuch"', $this->fixture->stderr());
        static::assertStringNotContainsString('Stack trace', $this->fixture->stderr());
    }

    /**
     * A failure that is not this package's own is reported as one line naming its class
     *
     * Requiring the bootstrap and compiling the module run application code and Ray.Di, so the
     * most ordinary compile failure — a missing binding — arrives as a foreign exception. Left
     * uncaught it escaped as a fatal with a stack trace and exit 255, outside the contract.
     */
    #[Test]
    public function reportsForeignThrowableAsRuntimeFailure(): void
    {
        $status = ($this->fixture->cli)(['bin', CliFixture::THROWING, $this->fixture->appDir, 'prod']);

        static::assertSame(1, $status);
        static::assertStringContainsString('LogicException: bootstrap blew up', $this->fixture->stderr());
        static::assertStringNotContainsString('Stack trace', $this->fixture->stderr());
    }
}

<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Support\CliFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function glob;

/** What the CLI does once the arguments are usable; CliRejectionTest covers the ones that are not */
#[CoversClass(Cli::class)]
final class CliTest extends TestCase
{
    /** Working directory and error stream */
    private CliFixture $fixture;

    /** System under test */
    private Cli $cli;

    /** {@inheritDoc} */
    protected function setUp(): void
    {
        $this->fixture = new CliFixture();
        $this->cli = new Cli($this->fixture->errorFile);
    }

    /** {@inheritDoc} */
    protected function tearDown(): void
    {
        $this->fixture->remove();
    }

    /** A mapped context compiles and reports success */
    #[Test]
    public function compilesMappedContext(): void
    {
        $status = ($this->cli)(['bin', CliFixture::VALID, $this->fixture->appDir, 'prod']);

        static::assertSame(0, $status);
        static::assertNotSame([], glob("{$this->fixture->appDir}/var/di/prod/*FakeCarInterface*.php"));
    }

    /** Asserts through the CLI's exit status */
    #[Test]
    public function treatsEmptyOverrideAsAbsent(): void
    {
        $status = ($this->cli)(['bin', CliFixture::VALID, $this->fixture->appDir, 'prod', '', '']);

        static::assertSame(0, $status);
        static::assertNotSame([], glob("{$this->fixture->appDir}/var/di/prod/*FakeCarInterface*.php"));
    }

    /** An explicit override compiles somewhere other than the conventional path */
    #[Test]
    public function honoursExplicitOverrides(): void
    {
        $compileDir = "{$this->fixture->baseDir}/elsewhere/di";

        $status = ($this->cli)([
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

    /** A package exception is reported as one line, without its class name or a trace */
    #[Test]
    public function reportsPackageExceptionAsRuntimeFailure(): void
    {
        $status = ($this->cli)(['bin', CliFixture::VALID, $this->fixture->appDir, 'nosuch']);

        static::assertSame(1, $status);
        static::assertStringContainsString('Unknown context "nosuch"', $this->fixture->stderr());
        static::assertStringNotContainsString('Stack trace', $this->fixture->stderr());
    }

    /** Asserts through the CLI's exit status */
    #[Test]
    public function reportsForeignThrowableAsRuntimeFailure(): void
    {
        $status = ($this->cli)(['bin', CliFixture::THROWING, $this->fixture->appDir, 'prod']);

        static::assertSame(1, $status);
        static::assertStringContainsString('LogicException: bootstrap blew up', $this->fixture->stderr());
        static::assertStringNotContainsString('Stack trace', $this->fixture->stderr());
    }
}

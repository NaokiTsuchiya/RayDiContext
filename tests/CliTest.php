<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Support\CliFixture;
use NaokiTsuchiya\RayDiContext\Support\Fs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function glob;

/** Argument handling and error reporting that never reaches a real compile; CliIntegrationTest covers the rest */
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
        $this->fixture = new CliFixture('cli_');
        $this->cli = new Cli($this->fixture->errorFile);
    }

    /** {@inheritDoc} */
    protected function tearDown(): void
    {
        $this->fixture->remove();
    }

    /** A package exception is reported as one line, without its class name or a trace */
    #[Test]
    public function reportsPackageExceptionAsRuntimeFailure(): void
    {
        $status = ($this->cli)(['bin', CliFixture::VALID, $this->fixture->appDir, 'nosuch']);

        static::assertSame(1, $status);
        static::assertStringContainsString('Unknown context "nosuch"', $this->fixture->stderr());
        static::assertStringNotContainsString('Stack trace', $this->fixture->stderr());
        static::assertStringNotContainsString('UnknownContext', $this->fixture->stderr());
    }

    /** A throwable unrelated to this package's exception hierarchy is still reported as one line, not a trace */
    #[Test]
    public function reportsForeignThrowableAsRuntimeFailure(): void
    {
        $status = ($this->cli)(['bin', CliFixture::THROWING, $this->fixture->appDir, 'prod']);

        static::assertSame(1, $status);
        static::assertStringContainsString('LogicException: bootstrap blew up', $this->fixture->stderr());
        static::assertStringNotContainsString('Stack trace', $this->fixture->stderr());
    }

    /** Too few arguments is a usage error naming the usage */
    #[Test]
    public function rejectsMissingArguments(): void
    {
        $status = ($this->cli)(['bin', CliFixture::VALID, $this->fixture->appDir]);

        static::assertSame(2, $status);
        static::assertStringContainsString('Usage:', $this->fixture->stderr());
    }

    /** More arguments than the CLI accepts is a usage error, and nothing is compiled */
    #[Test]
    public function rejectsTooManyArguments(): void
    {
        $status = ($this->cli)(['bin', CliFixture::VALID, $this->fixture->appDir, 'prod', '', '', 'extra']);

        static::assertSame(2, $status);
        static::assertStringContainsString('Too many arguments', $this->fixture->stderr());
        static::assertStringContainsString('Usage:', $this->fixture->stderr());
        static::assertSame([], glob("{$this->fixture->appDir}/var/di/prod/*.php"));
    }

    /** A bootstrap path that is not a file is a usage error naming the path */
    #[Test]
    public function rejectsMissingBootstrap(): void
    {
        $missing = "{$this->fixture->baseDir}/absent.php";

        $status = ($this->cli)(['bin', $missing, $this->fixture->appDir, 'prod']);

        static::assertSame(2, $status);
        static::assertStringContainsString('Bootstrap file not found', $this->fixture->stderr());
        static::assertStringContainsString($missing, $this->fixture->stderr());
    }

    /** A bootstrap returning something else is a usage error naming the required type */
    #[Test]
    public function rejectsBootstrapReturningWrongType(): void
    {
        $status = ($this->cli)(['bin', CliFixture::INVALID, $this->fixture->appDir, 'prod']);

        static::assertSame(2, $status);
        static::assertStringContainsString('must return', $this->fixture->stderr());
    }

    /** A missing appDir is a usage error */
    #[Test]
    public function rejectsMissingAppDir(): void
    {
        $missing = "{$this->fixture->baseDir}/absent";

        $status = ($this->cli)(['bin', CliFixture::VALID, $missing, 'prod']);

        static::assertSame(2, $status);
        static::assertStringContainsString('appDir does not exist', $this->fixture->stderr());
    }

    /** A bootstrap path that exists but is not a regular file is a usage error, not a require() warning */
    #[Test]
    public function rejectsBootstrapThatIsADirectory(): void
    {
        $status = ($this->cli)(['bin', $this->fixture->baseDir, $this->fixture->appDir, 'prod']);

        static::assertSame(2, $status);
        static::assertStringContainsString('Bootstrap file not found', $this->fixture->stderr());
        static::assertStringContainsString($this->fixture->baseDir, $this->fixture->stderr());
    }

    /** An appDir path that exists but is not a directory is a usage error, not an internal failure */
    #[Test]
    public function rejectsAppDirThatIsAFile(): void
    {
        $plainFile = "{$this->fixture->baseDir}/plainfile.txt";
        Fs::copyFile(Fs::SCRIPT, $plainFile);

        $status = ($this->cli)(['bin', CliFixture::VALID, $plainFile, 'prod']);

        static::assertSame(2, $status);
        static::assertStringContainsString('appDir does not exist or is not a directory', $this->fixture->stderr());
        static::assertStringContainsString($plainFile, $this->fixture->stderr());
    }

    /** An explicit compileDir/tmpDir pair that resolve to the same directory is a runtime failure, not a silent no-op */
    #[Test]
    public function reportsCompileDirEqualToTmpDirAsRuntimeFailure(): void
    {
        $shared = "{$this->fixture->baseDir}/shared";

        $status = ($this->cli)(['bin', CliFixture::VALID, $this->fixture->appDir, 'prod', $shared, $shared]);

        static::assertSame(1, $status);
        static::assertStringContainsString('must be different directories', $this->fixture->stderr());
    }
}

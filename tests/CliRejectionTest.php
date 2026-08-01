<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Support\CliFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function glob;

/** Arguments the CLI refuses before compiling anything, all of them exit status 2 */
#[CoversClass(Cli::class)]
final class CliRejectionTest extends TestCase
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
}

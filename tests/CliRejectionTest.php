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
 * Arguments the CLI refuses before compiling anything, all of them exit status 2
 */
#[CoversClass(Cli::class)]
final class CliRejectionTest extends TestCase
{
    /** Bootstrap mapping "prod" and "baked" to fake contexts */
    private const VALID = __DIR__ . '/Fixture/bootstrap_valid.php';

    /** @var non-empty-string Per-test working directory */
    private string $baseDir;

    /** @var non-empty-string App dir the compile would write below */
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
        $this->baseDir = __DIR__ . '/tmp/' . uniqid('cli_reject_', more_entropy: true);
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
     * Too few arguments is a usage error naming the usage
     *
     * @throws RuntimeException
     */
    #[Test]
    public function rejectsMissingArguments(): void
    {
        $status = ($this->cli)(['bin', self::VALID, $this->appDir]);

        static::assertSame(2, $status);
        static::assertStringContainsString('Usage:', $this->stderr());
    }

    /**
     * More arguments than the CLI accepts is a usage error, and nothing is compiled
     *
     * @throws RuntimeException
     */
    #[Test]
    public function rejectsTooManyArguments(): void
    {
        $status = ($this->cli)(['bin', self::VALID, $this->appDir, 'prod', '', '', 'extra']);

        static::assertSame(2, $status);
        static::assertStringContainsString('Too many arguments', $this->stderr());
        static::assertStringContainsString('Usage:', $this->stderr());
        static::assertSame([], glob("{$this->appDir}/var/di/prod/*.php"));
    }

    /**
     * A bootstrap path that is not a file is a usage error naming the path
     *
     * @throws RuntimeException
     */
    #[Test]
    public function rejectsMissingBootstrap(): void
    {
        $missing = "{$this->baseDir}/absent.php";

        $status = ($this->cli)(['bin', $missing, $this->appDir, 'prod']);

        static::assertSame(2, $status);
        static::assertStringContainsString('Bootstrap file not found', $this->stderr());
        static::assertStringContainsString($missing, $this->stderr());
    }

    /**
     * A bootstrap returning something else is a usage error naming the required type
     *
     * @throws RuntimeException
     */
    #[Test]
    public function rejectsBootstrapReturningWrongType(): void
    {
        $status = ($this->cli)(['bin', __DIR__ . '/Fixture/bootstrap_invalid.php', $this->appDir, 'prod']);

        static::assertSame(2, $status);
        static::assertStringContainsString('must return', $this->stderr());
    }

    /**
     * A missing appDir is a usage error; a relative one is a compile failure
     *
     * AppMeta rejects a relative appDir rather than resolving it, so both would arrive as
     * InvalidAppMeta. The CLI checks existence itself to keep the two apart: one is an argument
     * it can see is wrong before starting, the other is the compile refusing to run.
     *
     * @throws RuntimeException
     */
    #[Test]
    public function separatesMissingAppDirFromRelativeAppDir(): void
    {
        $missing = "{$this->baseDir}/absent";

        $status = ($this->cli)(['bin', self::VALID, $missing, 'prod']);

        static::assertSame(2, $status);
        static::assertStringContainsString('appDir does not exist', $this->stderr());

        $relative = ($this->cli)(['bin', self::VALID, '.', 'prod']);

        static::assertSame(1, $relative);
        static::assertStringContainsString('must be an absolute path', $this->stderr());
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

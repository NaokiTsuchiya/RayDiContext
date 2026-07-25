<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Fake\FakeProdContext;
use NaokiTsuchiya\RayDiContext\Fake\Fs;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function fclose;
use function file_put_contents;
use function glob;
use function mkdir;
use function proc_close;
use function proc_open;
use function sprintf;
use function stream_get_contents;
use function uniqid;

use const PHP_BINARY;

/**
 * End-to-end test for the bin/compile.php CLI
 */
final class BinCompileTest extends TestCase
{
    /** Path to the compile CLI under test */
    private const SCRIPT = __DIR__ . '/../bin/compile.php';

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
     */
    #[Test]
    public function compilesMappedContext(): void
    {
        $appDir = "{$this->baseDir}/app";
        $bootstrap = "{$this->baseDir}/bootstrap.php";
        file_put_contents($bootstrap, sprintf(
            "<?php\n\nreturn new %s(['prod' => %s::class]);\n",
            MapContextProvider::class,
            FakeProdContext::class,
        ));

        [$status, $stderr] = $this->runCli([$bootstrap, 'fake', $appDir, 'prod']);

        static::assertSame(0, $status, $stderr);
        static::assertNotSame([], glob("{$appDir}/var/di/prod/*FakeCarInterface*.php"));
    }

    /**
     * The CLI reports a usage error when arguments are missing
     */
    #[Test]
    public function failsWithUsageWhenArgumentsMissing(): void
    {
        [$status, $stderr] = $this->runCli([]);

        static::assertSame(2, $status);
        static::assertStringContainsString('Usage:', $stderr);
    }

    /**
     * The CLI rejects a bootstrap that does not return a provider
     */
    #[Test]
    public function failsWhenBootstrapReturnsWrongType(): void
    {
        $bootstrap = "{$this->baseDir}/bad-bootstrap.php";
        file_put_contents($bootstrap, data: "<?php\n\nreturn 'not a provider';\n");

        [$status, $stderr] = $this->runCli([$bootstrap, 'fake', "{$this->baseDir}/app", 'prod']);

        static::assertSame(2, $status);
        static::assertStringContainsString('must return', $stderr);
    }

    /**
     * Runs the compile CLI, returning its exit status and stderr
     *
     * @param list<string> $args
     *
     * @return array{int, string}
     */
    private function runCli(array $args): array
    {
        $command = [PHP_BINARY, self::SCRIPT, ...$args];
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $pipes = [];
        $process = proc_open($command, $descriptors, $pipes);
        static::assertIsResource($process);

        $stdout = $pipes[1] ?? null;
        $errPipe = $pipes[2] ?? null;
        static::assertIsResource($stdout);
        static::assertIsResource($errPipe);

        stream_get_contents($stdout);
        $stderr = stream_get_contents($errPipe);
        fclose($stdout);
        fclose($errPipe);
        $status = proc_close($process);

        return [$status, $stderr === false ? '' : $stderr];
    }
}

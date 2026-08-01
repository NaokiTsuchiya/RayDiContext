<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\ExceptionInterface;
use Throwable;

use function count;
use function file_put_contents;
use function is_dir;
use function is_file;
use function restore_error_handler;
use function set_error_handler;
use function sprintf;

/**
 * Argument handling and exit-status mapping for bin/ray-di-compile
 *
 * The exit status is this package's public contract; this class is not. It lives here rather
 * than in the bin script, which a "*.php" source glob never discovers, so the analyzer and the
 * coverage floor reach it.
 *
 * @internal Build on the exit-status contract, not on this class
 */
final class Cli
{
    /** Printed on its own for a short invocation, and appended to a too-long one */
    private const USAGE = "Usage: php bin/ray-di-compile <bootstrap> <appDir> <context> [compileDir] [tmpDir]\n";

    /** bootstrap, appDir and context, plus the optional compileDir/tmpDir overrides */
    private const MAX_ARGUMENTS = 5;

    /** bootstrap, appDir and context; the two directory overrides default from the app dir */
    private const REQUIRED_ARGUMENTS = 3;

    /**
     * @param string $errorStream Where a failure is written; a path rather than a stream so a
     *                            test can read it back without this class owning a handle
     */
    public function __construct(
        private readonly string $errorStream = 'php://stderr',
    ) {}

    /**
     * Compiles the requested context and returns the process exit status
     *
     * 0 compiled, 1 the compile failed, 2 the arguments were unusable — a public contract, so
     * every failure resolves to one of the three and writes a single line without a stack
     * trace, keeping a CI log readable.
     *
     * @param list<string> $argv Raw process arguments, script name first
     */
    public function __invoke(array $argv): int
    {
        $argumentCount = count($argv) - 1;
        if ($argumentCount < self::REQUIRED_ARGUMENTS) {
            return $this->usageError(self::USAGE);
        }

        if ($argumentCount > self::MAX_ARGUMENTS) {
            return $this->usageError(sprintf(
                "Too many arguments: at most %d are accepted, got %d.\n%s",
                self::MAX_ARGUMENTS,
                $argumentCount,
                self::USAGE,
            ));
        }

        $bootstrap = $argv[1] ?? '';
        $bootstrapExists = is_file($bootstrap);
        if (!$bootstrapExists) {
            return $this->usageError(sprintf("Bootstrap file not found: %s\n", $bootstrap));
        }

        // Checked here so a missing appDir stays a usage error; a relative one is a compile failure.
        $appDir = $argv[2] ?? '';
        $appDirExists = is_dir($appDir);
        if (!$appDirExists) {
            return $this->usageError(sprintf("appDir does not exist or is not a directory: %s\n", $appDir));
        }

        return $this->compile($bootstrap, $appDir, $argv[3] ?? '', $argv[4] ?? '', $argv[5] ?? '');
    }

    /**
     * Loads the bootstrap and runs the compile
     *
     * An empty override reads as "not given": an unset "$APP_COMPILE_DIR" arrives as "". Compared
     * against "" rather than for truthiness, so a directory named "0" survives.
     */
    private function compile(
        string $bootstrap,
        string $appDir,
        string $context,
        string $compileDir,
        string $tmpDir,
    ): int {
        try {
            /** @var mixed $provider */
            $provider = require $bootstrap;
            if (!$provider instanceof ContextProviderInterface) {
                return $this->usageError(sprintf(
                    "Bootstrap file %s must return a %s instance.\n",
                    $bootstrap,
                    ContextProviderInterface::class,
                ));
            }

            $meta = AppMeta::fromAppDir(
                $appDir,
                $context,
                $compileDir === '' ? null : $compileDir,
                $tmpDir === '' ? null : $tmpDir,
            );
            (new CompileRunner($provider))->run($meta);
        } catch (ExceptionInterface $e) {
            return $this->runtimeError("{$e->getMessage()}\n");
        } catch (Throwable $e) {
            // Named by class: a foreign throwable's message rarely says where it came from.
            return $this->runtimeError(sprintf("%s: %s\n", $e::class, $e->getMessage()));
        }

        return 0;
    }

    /**
     * Reports arguments the CLI cannot act on
     */
    private function usageError(string $message): int
    {
        $this->write($message);

        return 2;
    }

    /**
     * Reports a compile that was attempted and failed
     */
    private function runtimeError(string $message): int
    {
        $this->write($message);

        return 1;
    }

    /**
     * Writes the one line a failure gets, best effort
     *
     * An unwritable error stream has no second channel to be reported on, and this package does
     * not let E_WARNING reach its caller, so the diagnostic is dropped rather than raised.
     */
    private function write(string $message): void
    {
        set_error_handler(static fn(): bool => true);
        try {
            file_put_contents($this->errorStream, $message);
        } finally {
            restore_error_handler();
        }
    }
}

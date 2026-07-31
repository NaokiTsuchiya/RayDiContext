<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\ExceptionInterface;
use Throwable;

use function count;
use function file_put_contents;
use function is_dir;
use function is_file;
use function sprintf;

/**
 * Argument handling and exit-status mapping for bin/ray-di-compile
 *
 * The CLI's exit status is this package's public contract; this class is not. It lives here
 * rather than inside the bin script so that the analyzer and the coverage floor reach it — a
 * bin script carries no ".php" extension, so a "*.php" source glob never discovers it and it
 * was checked by nothing. Only the autoloader lookup stays in the script, since it has to run
 * before this class can be loaded at all.
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

        // Defaulted rather than indexed directly: the count above already guarantees the three
        // required arguments, but that guarantee is not one a reader — or an analyzer — can
        // recover from the index alone.
        $bootstrap = $argv[1] ?? '';
        $bootstrapExists = is_file($bootstrap);
        if (!$bootstrapExists) {
            return $this->usageError(sprintf("Bootstrap file not found: %s\n", $bootstrap));
        }

        // AppMeta::fromAppDir() rejects a relative appDir rather than resolving it, so a missing
        // directory and a relative one would both arrive as InvalidAppMeta. Existence is checked
        // here to keep the two apart: one is a usage error, the other is a compile failure.
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
     * An empty override reads as "not given": the documented invocation forwards
     * "$APP_COMPILE_DIR" through the shell, so an unset variable arrives as "". Compared
     * against "" rather than tested for truthiness, so a directory named "0" survives.
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
            // Not one of this package's own checks. Requiring the bootstrap and compiling the
            // module run application code and Ray.Di, and a missing binding — the most ordinary
            // compile failure there is — arrives as a Ray.Di exception. Left uncaught it escaped
            // as a fatal with a stack trace and exit 255, outside the contract entirely. The
            // class is named because at that point it is the part that says where the failure
            // came from; the message alone rarely does.
            return $this->runtimeError(sprintf("%s: %s\n", $e::class, $e->getMessage()));
        }

        return 0;
    }

    /**
     * Reports arguments the CLI cannot act on
     */
    private function usageError(string $message): int
    {
        file_put_contents($this->errorStream, $message);

        return 2;
    }

    /**
     * Reports a compile that was attempted and failed
     */
    private function runtimeError(string $message): int
    {
        file_put_contents($this->errorStream, $message);

        return 1;
    }
}

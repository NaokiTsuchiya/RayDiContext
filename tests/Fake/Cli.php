<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext\Fake;

use RuntimeException;

use function file_get_contents;
use function implode;
use function is_resource;
use function proc_close;
use function proc_open;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

use const PHP_BINARY;

/**
 * Runs a command in a subprocess for end-to-end tests
 */
final class Cli
{
    /**
     * Runs a PHP script with the given arguments, returning its exit status and stderr
     *
     * @param list<string> $args
     *
     * @return array{int, string}
     *
     * @throws RuntimeException When the subprocess cannot be started.
     */
    public static function run(string $script, array $args): array
    {
        [$status, , $stderr] = self::exec([PHP_BINARY, $script, ...$args]);

        return [$status, $stderr];
    }

    /**
     * Runs a command in a working directory, returning its exit status, stdout and stderr
     *
     * Both streams go to temp files rather than pipes: a pipe would have to be drained
     * while the child is still running, and a child that fills the other pipe meanwhile
     * would deadlock.
     *
     * @param list<string> $command Command and arguments, passed to proc_open unshelled
     * @param string|null  $cwd     Working directory, or null to inherit the caller's
     *
     * @return array{int, string, string} Exit status, stdout, stderr
     *
     * @throws RuntimeException When the temp files or the subprocess cannot be created.
     */
    public static function exec(array $command, ?string $cwd = null): array
    {
        $line = implode(' ', $command);
        $outFile = self::tempFile('cli_out_');
        $errFile = self::tempFile('cli_err_');
        $descriptors = [1 => ['file', $outFile, 'w'], 2 => ['file', $errFile, 'w']];
        $pipes = [];
        // An empty cwd is not a directory anyone means; proc_open takes null to inherit
        $process = proc_open($command, $descriptors, $pipes, $cwd === '' ? null : $cwd);
        if (!is_resource($process)) {
            throw new RuntimeException("Failed to start: {$line}");
        }

        $status = proc_close($process);
        $stdout = file_get_contents($outFile);
        $stderr = file_get_contents($errFile);
        unlink($outFile);
        unlink($errFile);

        return [$status, $stdout === false ? '' : $stdout, $stderr === false ? '' : $stderr];
    }

    /**
     * Creates an empty temp file, returning its path
     *
     * @return non-empty-string
     *
     * @throws RuntimeException When the file cannot be created.
     */
    private static function tempFile(string $prefix): string
    {
        $path = tempnam(sys_get_temp_dir(), prefix: $prefix);
        if ($path === false || $path === '') {
            throw new RuntimeException("Failed to create a temp file prefixed {$prefix}");
        }

        return $path;
    }
}

<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext\Fake;

use RuntimeException;

use function fclose;
use function is_resource;
use function proc_close;
use function proc_open;
use function stream_get_contents;

use const PHP_BINARY;

/**
 * Runs a PHP CLI script in a subprocess for end-to-end tests
 */
final class Cli
{
    /**
     * Runs the script with the given arguments, returning its exit status and stderr
     *
     * @param list<string> $args
     *
     * @return array{int, string}
     *
     * @throws RuntimeException When the subprocess or its pipes cannot be opened.
     */
    public static function run(string $script, array $args): array
    {
        $command = [PHP_BINARY, $script, ...$args];
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $pipes = [];
        $process = proc_open($command, $descriptors, $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException("Failed to start: {$script}");
        }

        $stdout = $pipes[1] ?? null;
        $errPipe = $pipes[2] ?? null;
        if (!is_resource($stdout) || !is_resource($errPipe)) {
            throw new RuntimeException("Failed to open pipes for: {$script}");
        }

        // Drain stdout so a full pipe buffer cannot deadlock the child
        stream_get_contents($stdout);
        $stderr = stream_get_contents($errPipe);
        fclose($stdout);
        fclose($errPipe);
        $status = proc_close($process);

        return [$status, $stderr === false ? '' : $stderr];
    }
}

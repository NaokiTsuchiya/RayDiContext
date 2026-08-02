<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext\Support;

use function file_get_contents;
use function mkdir;
use function uniqid;

/**
 * Working directory and error stream shared by the CLI test classes
 *
 * @internal
 */
final class CliFixture
{
    /** Bootstrap mapping "prod" and "baked" to fake contexts */
    public const VALID = __DIR__ . '/../Fixture/bootstrap_valid.php';

    /** Bootstrap returning a value that is not a provider */
    public const INVALID = __DIR__ . '/../Fixture/bootstrap_invalid.php';

    /** Bootstrap that throws something this package has no exception for */
    public const THROWING = __DIR__ . '/../Fixture/bootstrap_throwing.php';

    /** @var non-empty-string Per-test working directory */
    public readonly string $baseDir;

    /** @var non-empty-string App dir the compile writes below */
    public readonly string $appDir;

    /** @var non-empty-string File the CLI is pointed at instead of STDERR */
    public readonly string $errorFile;

    /** @param non-empty-string $prefix Names the working directory after the test class using it */
    public function __construct(string $prefix)
    {
        $this->baseDir = __DIR__ . '/../tmp/' . uniqid($prefix, more_entropy: true);
        $this->appDir = "{$this->baseDir}/app";
        mkdir("{$this->appDir}/var/tmp/prod", permissions: 0o755, recursive: true);
        $this->errorFile = "{$this->baseDir}/stderr.txt";
    }

    /** Returns what the CLI wrote to its error stream */
    public function stderr(): string
    {
        $written = file_get_contents($this->errorFile);

        return $written === false ? '' : $written;
    }

    /** Removes the working directory */
    public function remove(): void
    {
        Fs::removeDir($this->baseDir);
    }
}

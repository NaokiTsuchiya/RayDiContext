<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use JsonException;
use NaokiTsuchiya\RayDiContext\Fake\Cli;
use NaokiTsuchiya\RayDiContext\Fake\Fs;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function copy;
use function dirname;
use function explode;
use function file_put_contents;
use function getenv;
use function glob;
use function is_executable;
use function is_file;
use function json_encode;
use function mkdir;
use function uniqid;

use const DIRECTORY_SEPARATOR;
use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const PATH_SEPARATOR;
use const PHP_BINARY;

/**
 * End-to-end test for the way the package is actually distributed
 *
 * BinCompileTest runs bin/ray-di-compile straight out of this repository, where the
 * first autoloader candidate — the package's own vendor dir — always exists. Nobody who
 * runs `composer require naoki-tsuchiya/ray-di-context` takes that path. This test
 * builds a real consumer project against a path repository and drives the installed
 * vendor/bin entry, which fixes the bin name, the second autoloader candidate, the
 * Composer bin proxy, and that a compile actually succeeds from there.
 */
final class ConsumerInstallTest extends TestCase
{
    /** Version the path repository advertises; a dev version would fail minimum-stability */
    private const PACKAGE_VERSION = '1.0.0';

    /** Per-test working directory holding the package copy and the consumer project */
    private string $baseDir;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->baseDir = __DIR__ . '/tmp/' . uniqid('consumer_', more_entropy: true);
    }

    /**
     * {@inheritDoc}
     */
    protected function tearDown(): void
    {
        Fs::removeDir($this->baseDir);
    }

    /**
     * A consumer that installs the package compiles through vendor/bin/ray-di-compile
     *
     * @throws RuntimeException
     * @throws JsonException
     */
    #[Test]
    public function compilesThroughTheInstalledBinary(): void
    {
        $composer = self::findComposer();
        if ($composer === null) {
            static::markTestSkipped('composer was not found on PATH');
        }

        $consumer = "{$this->baseDir}/consumer";
        $this->createPackageCopy();
        $this->createConsumer($consumer);

        [$status, $stdout, $stderr] = Cli::exec([
            $composer,
            'install',
            '--no-interaction',
            '--no-progress',
            '--no-plugins',
        ], $consumer);
        static::assertSame(0, $status, $stdout . $stderr);

        // The installed package carries no vendor dir of its own, so the CLI's first
        // autoloader candidate misses and the distribution path is the one under test
        static::assertFileExists("{$consumer}/vendor/bin/ray-di-compile");
        static::assertFileDoesNotExist("{$consumer}/vendor/naoki-tsuchiya/ray-di-context/vendor/autoload.php");

        [$status, $stdout, $stderr] = Cli::exec([
            PHP_BINARY,
            'vendor/bin/ray-di-compile',
            'bootstrap.php',
            $consumer,
            'prod',
        ], $consumer);

        static::assertSame(0, $status, $stdout . $stderr);
        static::assertNotSame([], glob("{$consumer}/var/di/prod/*ConsumerCarInterface*.php"));
    }

    /**
     * Copies the files a released package ships into the path repository directory
     *
     * The copy deliberately leaves out vendor/: an installed package has no vendor dir,
     * and copying this repository's would hide the autoloader path being tested.
     */
    private function createPackageCopy(): void
    {
        $root = dirname(__DIR__);
        $package = "{$this->baseDir}/package";
        mkdir($package, permissions: 0o755, recursive: true);
        copy("{$root}/composer.json", "{$package}/composer.json");
        Fs::copyDir("{$root}/src", "{$package}/src");
        Fs::copyDir("{$root}/bin", "{$package}/bin");
    }

    /**
     * Writes the consumer project that requires the package from the path repository
     *
     * symlink:false is what makes this a distribution test. A symlinked path package
     * resolves __DIR__ back to the source tree, where neither autoloader candidate is
     * the consumer's own vendor/autoload.php.
     *
     * @throws JsonException When the manifest cannot be encoded.
     */
    private function createConsumer(string $consumer): void
    {
        mkdir($consumer, permissions: 0o755, recursive: true);
        $manifest = json_encode(
            [
                'repositories' => [
                    [
                        'type' => 'path',
                        'url' => '../package',
                        'options' => [
                            'symlink' => false,
                            'versions' => ['naoki-tsuchiya/ray-di-context' => self::PACKAGE_VERSION],
                        ],
                    ],
                ],
                'require' => ['naoki-tsuchiya/ray-di-context' => self::PACKAGE_VERSION],
            ],
            JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
        );
        file_put_contents("{$consumer}/composer.json", $manifest);
        copy(__DIR__ . '/Fixture/consumer_bootstrap.php', "{$consumer}/bootstrap.php");
    }

    /**
     * Returns the composer executable on PATH, or null when there is none
     */
    private static function findComposer(): ?string
    {
        $path = getenv('PATH');
        if ($path === false) {
            return null;
        }

        foreach (explode(PATH_SEPARATOR, $path) as $dir) {
            $candidate = $dir . DIRECTORY_SEPARATOR . 'composer';
            $isExecutable = is_file($candidate) && is_executable($candidate);
            if ($isExecutable) {
                return $candidate;
            }
        }

        return null;
    }
}

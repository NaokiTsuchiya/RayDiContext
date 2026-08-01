<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\ExceptionInterface;
use NaokiTsuchiya\RayDiContext\Support\Fs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function chmod;
use function copy;
use function fileperms;
use function mkdir;
use function symlink;
use function uniqid;

#[CoversClass(PermissionNormalizer::class)]
final class PermissionNormalizerTest extends TestCase
{
    /** Stands in for a compiled script the tests assert the mode of */
    private const SCRIPT = __DIR__ . '/Fixture/script.php';

    /** @var non-empty-string Per-test working directory */
    private string $baseDir;

    /** @var non-empty-string Directory standing in for the compile dir */
    private string $compileDir;

    /** {@inheritDoc} */
    protected function setUp(): void
    {
        $this->baseDir = __DIR__ . '/tmp/' . uniqid('perm_', more_entropy: true);
        $this->compileDir = "{$this->baseDir}/di";
        mkdir($this->compileDir, permissions: 0o700, recursive: true);
        chmod($this->compileDir, permissions: 0o700); // mkdir() applies the umask, chmod() does not
    }

    /** {@inheritDoc} */
    protected function tearDown(): void
    {
        Fs::removeDir($this->baseDir);
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function normalizesFilesAndDirectories(): void
    {
        $nested = "{$this->compileDir}/nested";
        mkdir($nested, permissions: 0o700);
        chmod($nested, permissions: 0o700);
        $this->copyScript("{$this->compileDir}/script.php", mode: 0o600);
        $this->copyScript("{$nested}/script.php", mode: 0o600);

        (new PermissionNormalizer())($this->compileDir);

        static::assertSame(0o755, $this->mode($this->compileDir));
        static::assertSame(0o755, $this->mode($nested));
        static::assertSame(0o644, $this->mode("{$this->compileDir}/script.php"));
        static::assertSame(0o644, $this->mode("{$nested}/script.php"));
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function leavesAlreadyReadableEntriesAlone(): void
    {
        $nested = "{$this->compileDir}/nested";
        mkdir($nested, permissions: 0o775);
        chmod($nested, permissions: 0o775);
        $this->copyScript("{$this->compileDir}/script.php", mode: 0o664);

        (new PermissionNormalizer())($this->compileDir);

        static::assertSame(0o775, $this->mode($nested));
        static::assertSame(0o664, $this->mode("{$this->compileDir}/script.php"));
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function doesNotFollowSymlinks(): void
    {
        $target = "{$this->baseDir}/outside";
        mkdir($target, permissions: 0o700);
        chmod($target, permissions: 0o700);
        $this->copyScript("{$target}/script.php", mode: 0o600);
        symlink($target, "{$this->compileDir}/link");

        (new PermissionNormalizer())($this->compileDir);

        static::assertSame(0o700, $this->mode($target));
        static::assertSame(0o600, $this->mode("{$target}/script.php"));
    }

    /** Copies the fixture script in and gives it a mode the umask cannot narrow */
    private function copyScript(string $path, int $mode): void
    {
        copy(self::SCRIPT, $path);
        chmod($path, $mode);
    }

    /** Returns the permission bits of a path */
    private function mode(string $path): int
    {
        return (int) fileperms($path) & 0o777;
    }
}

<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\ExceptionInterface;
use NaokiTsuchiya\RayDiContext\Support\CompileDirFixture;
use NaokiTsuchiya\RayDiContext\Support\Fs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function chmod;
use function copy;
use function mkdir;
use function symlink;

#[CoversClass(PermissionNormalizer::class)]
final class PermissionNormalizerTest extends TestCase
{
    /** Stands in for a compiled script the tests assert the mode of */
    private const SCRIPT = __DIR__ . '/Fixture/script.php';

    /** Working directory holding the compile dir these tests start from */
    private CompileDirFixture $fixture;

    /** @var non-empty-string Directory standing in for the compile dir */
    private string $compileDir;

    /** {@inheritDoc} */
    protected function setUp(): void
    {
        $this->fixture = new CompileDirFixture('perm_');
        $this->compileDir = $this->fixture->compileDir;
    }

    /** {@inheritDoc} */
    protected function tearDown(): void
    {
        $this->fixture->remove();
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

        static::assertSame(0o755, Fs::mode($this->compileDir));
        static::assertSame(0o755, Fs::mode($nested));
        static::assertSame(0o644, Fs::mode("{$this->compileDir}/script.php"));
        static::assertSame(0o644, Fs::mode("{$nested}/script.php"));
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

        static::assertSame(0o775, Fs::mode($nested));
        static::assertSame(0o664, Fs::mode("{$this->compileDir}/script.php"));
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function doesNotFollowSymlinks(): void
    {
        $target = "{$this->fixture->baseDir}/outside";
        mkdir($target, permissions: 0o700);
        chmod($target, permissions: 0o700);
        $this->copyScript("{$target}/script.php", mode: 0o600);
        symlink($target, "{$this->compileDir}/link");

        (new PermissionNormalizer())($this->compileDir);

        static::assertSame(0o700, Fs::mode($target));
        static::assertSame(0o600, Fs::mode("{$target}/script.php"));
    }

    /** Copies the fixture script in and gives it a mode the umask cannot narrow */
    private function copyScript(string $path, int $mode): void
    {
        copy(self::SCRIPT, $path);
        chmod($path, $mode);
    }
}

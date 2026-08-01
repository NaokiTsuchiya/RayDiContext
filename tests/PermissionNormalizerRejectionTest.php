<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\ChmodFailed;
use NaokiTsuchiya\RayDiContext\Exception\CompileDirNotFound;
use NaokiTsuchiya\RayDiContext\Exception\CompileDirNotReadable;
use NaokiTsuchiya\RayDiContext\Fake\Fs;
use NaokiTsuchiya\RayDiContext\Fake\PermissionBits;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function chmod;
use function copy;
use function fileperms;
use function mkdir;
use function uniqid;

/**
 * The normalizer refuses a path it cannot normalize, without changing anything
 */
#[CoversClass(PermissionNormalizer::class)]
final class PermissionNormalizerRejectionTest extends TestCase
{
    /** Stands in for a compiled script the tests assert the mode of */
    private const SCRIPT = __DIR__ . '/Fixture/script.php';

    /** @var non-empty-string Per-test working directory */
    private string $baseDir;

    /** @var non-empty-string Directory standing in for the compile dir */
    private string $compileDir;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->baseDir = __DIR__ . '/tmp/' . uniqid('perm_reject_', more_entropy: true);
        $this->compileDir = "{$this->baseDir}/di";
        mkdir($this->compileDir, permissions: 0o700, recursive: true);
        chmod($this->compileDir, permissions: 0o700); // mkdir() applies the umask, chmod() does not
    }

    /**
     * {@inheritDoc}
     */
    protected function tearDown(): void
    {
        Fs::removeDir($this->baseDir);
    }

    /**
     * A path that is a file rather than a directory is rejected, changing nothing
     *
     * Without the check the file is chmod'ed to 0755 and only then fails, so a call that
     * did not succeed leaves a side effect behind — and it fails as an SPL exception from
     * FilesystemIterator rather than as an exception of this package.
     *
     * @throws ChmodFailed
     * @throws CompileDirNotReadable
     * @throws RuntimeException
     */
    #[Test]
    public function rejectsAPathThatIsNotADirectory(): void
    {
        $script = "{$this->compileDir}/script.php";
        copy(self::SCRIPT, $script);
        chmod($script, permissions: 0o600);

        try {
            (new PermissionNormalizer())($script);
            static::fail('CompileDirNotFound was not thrown');
        } catch (CompileDirNotFound $e) {
            static::assertStringContainsString($script, $e->getMessage());
            // Nothing was chmod'ed: the rejected path still has the mode it had
            static::assertSame(0o600, $this->mode($script));
            static::assertSame(0o700, $this->mode($this->compileDir));
        }
    }

    /**
     * A path that does not exist is rejected by name
     *
     * @throws ChmodFailed
     * @throws CompileDirNotReadable
     * @throws RuntimeException
     */
    #[Test]
    public function rejectsAMissingPath(): void
    {
        $missing = "{$this->compileDir}/absent";

        try {
            (new PermissionNormalizer())($missing);
            static::fail('CompileDirNotFound was not thrown');
        } catch (CompileDirNotFound $e) {
            static::assertStringContainsString($missing, $e->getMessage());
            static::assertFileDoesNotExist($missing);
            static::assertSame(0o700, $this->mode($this->compileDir));
        }
    }

    /**
     * A compile dir the process cannot list fails as a package exception
     *
     * 0005 is the shape that gets past the mode check: the world bits it looks at are
     * set, but POSIX resolves the owner class first, so the owner is denied and
     * FilesystemIterator raises an SPL exception that would otherwise escape the
     * declared contract.
     *
     * @throws ChmodFailed
     * @throws CompileDirNotFound
     * @throws RuntimeException
     */
    #[Test]
    public function rejectsACompileDirItCannotList(): void
    {
        PermissionBits::skipUnlessEnforced($this->baseDir);

        chmod($this->compileDir, permissions: 0o005);

        try {
            (new PermissionNormalizer())($this->compileDir);
            static::fail('CompileDirNotReadable was not thrown');
        } catch (CompileDirNotReadable $e) {
            static::assertStringContainsString($this->compileDir, $e->getMessage());
        } finally {
            chmod($this->compileDir, permissions: 0o700); // tearDown has to be able to remove it
        }
    }

    /**
     * An unlistable directory nested in a normal compile dir fails the same way
     *
     * @throws ChmodFailed
     * @throws CompileDirNotFound
     * @throws RuntimeException
     */
    #[Test]
    public function rejectsANestedDirectoryItCannotList(): void
    {
        PermissionBits::skipUnlessEnforced($this->baseDir);

        $nested = "{$this->compileDir}/nested";
        mkdir($nested, permissions: 0o700);
        chmod($nested, permissions: 0o005);

        try {
            (new PermissionNormalizer())($this->compileDir);
            static::fail('CompileDirNotReadable was not thrown');
        } catch (CompileDirNotReadable $e) {
            static::assertStringContainsString($nested, $e->getMessage());
        } finally {
            chmod($nested, permissions: 0o700);
        }
    }

    /**
     * A compile dir the process cannot traverse fails as a package exception
     *
     * 0405 is the other half of that family: read is granted, so the listing opens and
     * only the stat() of each entry is denied — which leaked a PHP warning per entry
     * from fileperms() and chmod() before anything failed.
     *
     * @throws ChmodFailed
     * @throws CompileDirNotFound
     * @throws RuntimeException
     */
    #[Test]
    public function rejectsACompileDirItCannotTraverse(): void
    {
        PermissionBits::skipUnlessEnforced($this->baseDir);

        $script = "{$this->compileDir}/script.php";
        copy(self::SCRIPT, $script);
        chmod($script, permissions: 0o600);
        chmod($this->compileDir, permissions: 0o405);

        try {
            (new PermissionNormalizer())($this->compileDir);
            static::fail('CompileDirNotReadable was not thrown');
        } catch (CompileDirNotReadable $e) {
            static::assertStringContainsString($this->compileDir, $e->getMessage());
        } finally {
            chmod($this->compileDir, permissions: 0o700); // tearDown has to be able to remove it
        }

        // Nothing inside was touched: no entry was ever reached
        static::assertSame(0o600, $this->mode($script));
    }

    /**
     * An untraversable directory nested in a normal compile dir fails the same way
     *
     * @throws ChmodFailed
     * @throws CompileDirNotFound
     * @throws RuntimeException
     */
    #[Test]
    public function rejectsANestedDirectoryItCannotTraverse(): void
    {
        PermissionBits::skipUnlessEnforced($this->baseDir);

        $nested = "{$this->compileDir}/nested";
        mkdir($nested, permissions: 0o700);
        $inner = "{$nested}/inner.php";
        copy(self::SCRIPT, $inner);
        chmod($inner, permissions: 0o600);
        chmod($nested, permissions: 0o405);

        try {
            (new PermissionNormalizer())($this->compileDir);
            static::fail('CompileDirNotReadable was not thrown');
        } catch (CompileDirNotReadable $e) {
            static::assertStringContainsString($nested, $e->getMessage());
            static::assertSame(0o405, $this->mode($nested));
        } finally {
            chmod($nested, permissions: 0o700);
        }

        static::assertSame(0o600, $this->mode($inner));
    }

    /**
     * Returns the permission bits of a path
     */
    private function mode(string $path): int
    {
        return (int) fileperms($path) & 0o777;
    }
}

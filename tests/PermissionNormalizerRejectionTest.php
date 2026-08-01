<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\CompileDirNotFound;
use NaokiTsuchiya\RayDiContext\Exception\CompileDirNotReadable;
use NaokiTsuchiya\RayDiContext\Exception\ExceptionInterface;
use NaokiTsuchiya\RayDiContext\Support\CompileDirFixture;
use NaokiTsuchiya\RayDiContext\Support\Fs;
use NaokiTsuchiya\RayDiContext\Support\PermissionBits;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function chmod;
use function copy;
use function mkdir;

/** The normalizer refuses a path it cannot normalize, without changing anything */
#[CoversClass(PermissionNormalizer::class)]
final class PermissionNormalizerRejectionTest extends TestCase
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
        $this->fixture = new CompileDirFixture('perm_reject_');
        $this->compileDir = $this->fixture->compileDir;
    }

    /** {@inheritDoc} */
    protected function tearDown(): void
    {
        $this->fixture->remove();
    }

    /** @throws ExceptionInterface */
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
            static::assertSame(0o600, Fs::mode($script));
            static::assertSame(0o700, Fs::mode($this->compileDir));
        }
    }

    /** @throws ExceptionInterface */
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
            static::assertSame(0o700, Fs::mode($this->compileDir));
        }
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function rejectsACompileDirItCannotList(): void
    {
        PermissionBits::skipUnlessEnforced($this->fixture->baseDir);

        chmod($this->compileDir, permissions: 0o005);

        try {
            (new PermissionNormalizer())($this->compileDir);
            static::fail('CompileDirNotReadable was not thrown');
        } catch (CompileDirNotReadable $e) {
            static::assertStringContainsString($this->compileDir, $e->getMessage());
        } finally {
            chmod($this->compileDir, permissions: 0o700);
        }
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function rejectsANestedDirectoryItCannotList(): void
    {
        PermissionBits::skipUnlessEnforced($this->fixture->baseDir);

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

    /** @throws ExceptionInterface */
    #[Test]
    public function rejectsACompileDirItCannotTraverse(): void
    {
        PermissionBits::skipUnlessEnforced($this->fixture->baseDir);

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
            chmod($this->compileDir, permissions: 0o700);
        }

        static::assertSame(0o600, Fs::mode($script));
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function rejectsANestedDirectoryItCannotTraverse(): void
    {
        PermissionBits::skipUnlessEnforced($this->fixture->baseDir);

        $nested = "{$this->compileDir}/nested";
        mkdir($nested, permissions: 0o700);
        chmod($nested, permissions: 0o700);
        $inner = "{$nested}/inner.php";
        copy(self::SCRIPT, $inner);
        chmod($inner, permissions: 0o600);
        chmod($nested, permissions: 0o405);

        try {
            (new PermissionNormalizer())($this->compileDir);
            static::fail('CompileDirNotReadable was not thrown');
        } catch (CompileDirNotReadable $e) {
            static::assertStringContainsString($nested, $e->getMessage());
            static::assertSame(0o405, Fs::mode($nested));
        } finally {
            chmod($nested, permissions: 0o700);
        }

        static::assertSame(0o600, Fs::mode($inner));
    }
}

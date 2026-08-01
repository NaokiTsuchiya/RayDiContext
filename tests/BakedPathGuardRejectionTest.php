<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\CompileDirNotFound;
use NaokiTsuchiya\RayDiContext\Exception\CompileDirNotReadable;
use NaokiTsuchiya\RayDiContext\Exception\ExceptionInterface;
use NaokiTsuchiya\RayDiContext\Support\Fs;
use NaokiTsuchiya\RayDiContext\Support\PermissionBits;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function chmod;
use function file_put_contents;
use function mkdir;
use function uniqid;

/**
 * The guard refuses a compile dir it cannot open, as a package exception rather than a bare SPL one
 */
#[CoversClass(BakedPathGuard::class)]
final class BakedPathGuardRejectionTest extends TestCase
{
    /** @var non-empty-string Per-test working directory */
    private string $baseDir;

    /** Meta whose compile dir this test controls the shape of */
    private AppMeta $meta;

    /** System under test */
    private BakedPathGuard $guard;

    /** @throws ExceptionInterface */
    protected function setUp(): void
    {
        $this->baseDir = __DIR__ . '/tmp/' . uniqid('guard_reject_', more_entropy: true);
        $appDir = "{$this->baseDir}/app";
        $this->meta = new AppMeta($appDir, 'prod', "{$this->baseDir}/di", "{$this->baseDir}/rw-tmp");
        $this->guard = new BakedPathGuard();
    }

    /** {@inheritDoc} */
    protected function tearDown(): void
    {
        Fs::removeDir($this->baseDir);
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function rejectsAMissingCompileDir(): void
    {
        try {
            ($this->guard)($this->meta);
            static::fail('CompileDirNotFound was not thrown');
        } catch (CompileDirNotFound $e) {
            static::assertStringContainsString($this->meta->compileDir, $e->getMessage());
        }
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function rejectsACompileDirThatIsAFile(): void
    {
        mkdir($this->baseDir, permissions: 0o755, recursive: true);
        file_put_contents($this->meta->compileDir, data: '');

        try {
            ($this->guard)($this->meta);
            static::fail('CompileDirNotFound was not thrown');
        } catch (CompileDirNotFound $e) {
            static::assertStringContainsString($this->meta->compileDir, $e->getMessage());
        }
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function rejectsACompileDirItCannotList(): void
    {
        PermissionBits::skipUnlessEnforced($this->baseDir);

        mkdir($this->meta->compileDir, permissions: 0o700, recursive: true);
        chmod($this->meta->compileDir, permissions: 0o005);

        try {
            ($this->guard)($this->meta);
            static::fail('CompileDirNotReadable was not thrown');
        } catch (CompileDirNotReadable $e) {
            static::assertStringContainsString($this->meta->compileDir, $e->getMessage());
        } finally {
            chmod($this->meta->compileDir, permissions: 0o700);
        }
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function rejectsACompileDirItCannotTraverse(): void
    {
        PermissionBits::skipUnlessEnforced($this->baseDir);

        mkdir($this->meta->compileDir, permissions: 0o700, recursive: true);
        file_put_contents("{$this->meta->compileDir}/a.php", data: '<?php return new stdClass();');
        chmod($this->meta->compileDir, permissions: 0o405);

        try {
            ($this->guard)($this->meta);
            static::fail('CompileDirNotReadable was not thrown');
        } catch (CompileDirNotReadable $e) {
            static::assertStringContainsString($this->meta->compileDir, $e->getMessage());
        } finally {
            chmod($this->meta->compileDir, permissions: 0o700);
        }
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function rejectsANestedDirectoryItCannotList(): void
    {
        PermissionBits::skipUnlessEnforced($this->baseDir);

        $nested = "{$this->meta->compileDir}/nested";
        mkdir($nested, permissions: 0o700, recursive: true);
        chmod($nested, permissions: 0o005);

        try {
            ($this->guard)($this->meta);
            static::fail('CompileDirNotReadable was not thrown');
        } catch (CompileDirNotReadable $e) {
            static::assertStringContainsString($this->meta->compileDir, $e->getMessage());
        } finally {
            chmod($nested, permissions: 0o700);
        }
    }
}

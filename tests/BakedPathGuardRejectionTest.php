<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\BakedPathFound;
use NaokiTsuchiya\RayDiContext\Exception\CompileDirNotFound;
use NaokiTsuchiya\RayDiContext\Exception\CompileDirNotReadable;
use NaokiTsuchiya\RayDiContext\Exception\InvalidAppMeta;
use NaokiTsuchiya\RayDiContext\Exception\ScriptNotReadable;
use NaokiTsuchiya\RayDiContext\Fake\Fs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function chmod;
use function file_put_contents;
use function mkdir;
use function uniqid;

/**
 * The guard refuses a compile dir it cannot open, as a package exception rather than a
 * bare SPL one
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

    /**
     * {@inheritDoc}
     *
     * @throws InvalidAppMeta
     */
    protected function setUp(): void
    {
        $this->baseDir = __DIR__ . '/tmp/' . uniqid('guard_reject_', more_entropy: true);
        $appDir = "{$this->baseDir}/app";
        $this->meta = new AppMeta($appDir, 'prod', "{$this->baseDir}/di", "{$this->baseDir}/rw-tmp");
        $this->guard = new BakedPathGuard();
    }

    /**
     * {@inheritDoc}
     */
    protected function tearDown(): void
    {
        Fs::removeDir($this->baseDir);
    }

    /**
     * A compile dir that does not exist is rejected by name, instead of RecursiveDirectoryIterator's
     * bare UnexpectedValueException
     *
     * @throws BakedPathFound
     * @throws CompileDirNotReadable
     * @throws ScriptNotReadable
     */
    #[Test]
    public function rejectsAMissingCompileDir(): void
    {
        try {
            ($this->guard)($this->meta->compileDir, $this->meta);
            static::fail('CompileDirNotFound was not thrown');
        } catch (CompileDirNotFound $e) {
            static::assertStringContainsString($this->meta->compileDir, $e->getMessage());
        }
    }

    /**
     * A compile dir path that is a regular file is rejected the same way
     *
     * @throws BakedPathFound
     * @throws CompileDirNotReadable
     * @throws ScriptNotReadable
     */
    #[Test]
    public function rejectsACompileDirThatIsAFile(): void
    {
        mkdir($this->baseDir, permissions: 0o755, recursive: true);
        file_put_contents($this->meta->compileDir, data: '');

        try {
            ($this->guard)($this->meta->compileDir, $this->meta);
            static::fail('CompileDirNotFound was not thrown');
        } catch (CompileDirNotFound $e) {
            static::assertStringContainsString($this->meta->compileDir, $e->getMessage());
        }
    }

    /**
     * A compile dir the process cannot list fails as a package exception
     *
     * 0005 is the shape that gets past a bare mode check: the world bits are set, but
     * POSIX resolves the owner class first, so the owner is denied and
     * RecursiveDirectoryIterator raises an SPL exception that would otherwise escape the
     * declared contract.
     *
     * @throws BakedPathFound
     * @throws CompileDirNotFound
     * @throws ScriptNotReadable
     */
    #[Test]
    public function rejectsACompileDirItCannotList(): void
    {
        mkdir($this->meta->compileDir, permissions: 0o700, recursive: true);
        chmod($this->meta->compileDir, permissions: 0o005);

        try {
            ($this->guard)($this->meta->compileDir, $this->meta);
            static::fail('CompileDirNotReadable was not thrown');
        } catch (CompileDirNotReadable $e) {
            static::assertStringContainsString($this->meta->compileDir, $e->getMessage());
        } finally {
            chmod($this->meta->compileDir, permissions: 0o700); // tearDown has to be able to remove it
        }
    }

    /**
     * A compile dir the process cannot traverse fails as a package exception
     *
     * 0405 is the other half of that family: read is granted so listing opens, but every
     * per-entry stat() the iterator performs while traversing is denied.
     *
     * @throws BakedPathFound
     * @throws CompileDirNotFound
     * @throws ScriptNotReadable
     */
    #[Test]
    public function rejectsACompileDirItCannotTraverse(): void
    {
        mkdir($this->meta->compileDir, permissions: 0o700, recursive: true);
        file_put_contents("{$this->meta->compileDir}/a.php", data: '<?php return new stdClass();');
        chmod($this->meta->compileDir, permissions: 0o405);

        try {
            ($this->guard)($this->meta->compileDir, $this->meta);
            static::fail('CompileDirNotReadable was not thrown');
        } catch (CompileDirNotReadable $e) {
            static::assertStringContainsString($this->meta->compileDir, $e->getMessage());
        } finally {
            chmod($this->meta->compileDir, permissions: 0o700); // tearDown has to be able to remove it
        }
    }

    /**
     * An unlistable directory nested below a normal compile dir fails the same way, once
     * RecursiveDirectoryIterator opens it mid-traversal
     *
     * @throws BakedPathFound
     * @throws CompileDirNotFound
     * @throws ScriptNotReadable
     */
    #[Test]
    public function rejectsANestedDirectoryItCannotList(): void
    {
        $nested = "{$this->meta->compileDir}/nested";
        mkdir($nested, permissions: 0o700, recursive: true);
        chmod($nested, permissions: 0o005);

        try {
            ($this->guard)($this->meta->compileDir, $this->meta);
            static::fail('CompileDirNotReadable was not thrown');
        } catch (CompileDirNotReadable $e) {
            static::assertStringContainsString($this->meta->compileDir, $e->getMessage());
        } finally {
            chmod($nested, permissions: 0o700);
        }
    }
}

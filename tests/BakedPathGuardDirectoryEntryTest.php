<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\BakedPathFound;
use NaokiTsuchiya\RayDiContext\Exception\InvalidAppMeta;
use NaokiTsuchiya\RayDiContext\Exception\ScriptNotReadable;
use NaokiTsuchiya\RayDiContext\Fake\Fs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function file_put_contents;
use function mkdir;
use function symlink;
use function uniqid;

/**
 * A compileDir entry whose name ends in ".php" is not necessarily a script: it can be a
 * directory, or a symlink to one. The guard must not rely on file_get_contents() to tell
 * the difference, since a directory path fed to it returns false on Linux but an empty
 * string on macOS — the exact inconsistency this class guards against.
 */
#[CoversClass(BakedPathGuard::class)]
final class BakedPathGuardDirectoryEntryTest extends TestCase
{
    /** Per-test working directory */
    private string $baseDir;

    /** Meta whose tmp dir lives outside the app dir */
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
        $this->baseDir = __DIR__ . '/tmp/' . uniqid('guard_dir_', more_entropy: true);
        $appDir = "{$this->baseDir}/app";
        $this->meta = new AppMeta($appDir, 'prod', "{$appDir}/var/di/prod", "{$this->baseDir}/rw-tmp");
        mkdir($this->meta->compileDir, permissions: 0o755, recursive: true);
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
     * A symlink to a directory, named like a script, is rejected instead of silently
     * skipped
     *
     * RecursiveDirectoryIterator does not descend into symlinks by default, so a symlink
     * named e.g. "cache.php" pointing at a directory is visited as a leaf, not recursed
     * into. Guarding on is_file() before reading it means the rejection no longer depends
     * on how file_get_contents() happens to report a directory path on the host platform.
     *
     * @throws BakedPathFound
     * @throws RuntimeException
     */
    #[Test]
    public function throwsOnSymlinkToDirectoryNamedLikeAScript(): void
    {
        $targetDir = "{$this->baseDir}/link-target";
        mkdir($targetDir, permissions: 0o755, recursive: true);
        $link = "{$this->meta->compileDir}/cache.php";
        symlink($targetDir, $link);

        $this->expectException(ScriptNotReadable::class);
        $this->expectExceptionMessage($link);

        ($this->guard)($this->meta->compileDir, $this->meta);
    }

    /**
     * A real directory named like a script is traversed as a directory; scripts inside
     * it are still scanned normally
     *
     * @throws BakedPathFound
     * @throws RuntimeException
     */
    #[Test]
    public function scansScriptsInsideADirectoryNamedLikeAScript(): void
    {
        $nestedDir = "{$this->meta->compileDir}/cache.php";
        mkdir($nestedDir, permissions: 0o755, recursive: true);
        file_put_contents("{$nestedDir}/nested.php", "<?php return '{$this->meta->appDir}/src/Index.php';");

        $this->expectException(BakedPathFound::class);

        ($this->guard)($this->meta->compileDir, $this->meta);
    }
}

<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\BakedPathFound;
use NaokiTsuchiya\RayDiContext\Exception\InvalidAppMeta;
use NaokiTsuchiya\RayDiContext\Fake\Fs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function file_put_contents;
use function mkdir;
use function symlink;
use function uniqid;

#[CoversClass(BakedPathGuard::class)]
#[CoversClass(BakedPathScanner::class)]
final class BakedPathGuardBoundaryTest extends TestCase
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
        $this->baseDir = __DIR__ . '/tmp/' . uniqid('guard_boundary_', more_entropy: true);
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
     * A path inside the compile dir is allowed: it is baked into the image with the scripts
     *
     * @throws BakedPathFound
     * @throws RuntimeException
     */
    #[Test]
    public function allowsPathInsideCompileDir(): void
    {
        file_put_contents(
            "{$this->meta->compileDir}/script-path.php",
            data: "<?php return '{$this->meta->compileDir}/scripts/x.php';",
        );

        ($this->guard)($this->meta->compileDir, $this->meta);

        $this->expectNotToPerformAssertions();
    }

    /**
     * A tmpDir nested under the compile dir is still detected
     *
     * A read-only compile dir can never host the writable tmp dir, so this literal must fail CI.
     *
     * @throws RuntimeException
     */
    #[Test]
    public function detectsTmpDirNestedUnderCompileDir(): void
    {
        $meta = new AppMeta(
            $this->meta->appDir,
            $this->meta->context,
            $this->meta->compileDir,
            "{$this->meta->compileDir}/tmp",
        );
        file_put_contents("{$this->meta->compileDir}/baked.php", data: "<?php return '{$meta->tmpDir}/cache';");

        $this->expectException(BakedPathFound::class);

        ($this->guard)($this->meta->compileDir, $meta);
    }

    /**
     * A sibling path sharing the compile dir as a string prefix is detected
     *
     * @throws RuntimeException
     */
    #[Test]
    public function detectsPathWithCompileDirStringPrefix(): void
    {
        // compileDir is …/var/di/prod; this is …/var/di/production_logs/app.log
        file_put_contents(
            "{$this->meta->compileDir}/baked.php",
            data: "<?php return '{$this->meta->compileDir}uction_logs/app.log';",
        );

        $this->expectException(BakedPathFound::class);

        ($this->guard)($this->meta->compileDir, $this->meta);
    }

    /**
     * A baked literal spelled through a symlinked appDir is still detected
     *
     * appDir is no longer realpath()-resolved by fromAppDir(), so a caller whose appDir is
     * reached through a symlink (e.g. Capistrano's /app -> /releases/current) binds paths
     * spelled with the link, not the target. The guard must catch a literal spelled the
     * same way fromAppDir() actually bound it. Constructing AppMeta directly (bypassing
     * fromAppDir()) would not reproduce the bug this test guards against, since the
     * constructor itself never resolved symlinks — only fromAppDir()'s now-removed
     * realpath() call did.
     *
     * @throws RuntimeException
     * @throws InvalidAppMeta
     */
    #[Test]
    public function detectsBakedPathThroughSymlinkedAppDir(): void
    {
        $real = "{$this->baseDir}/real/app";
        mkdir($real, permissions: 0o755, recursive: true);
        $link = "{$this->baseDir}/app-link";
        symlink($real, $link);

        $meta = AppMeta::fromAppDir($link, 'prod', "{$link}/var/di/prod", "{$link}/var/tmp/prod");
        mkdir($meta->compileDir, permissions: 0o755, recursive: true);
        file_put_contents("{$meta->compileDir}/baked.php", data: "<?php return '{$link}/storage';");

        $this->expectException(BakedPathFound::class);

        ($this->guard)($meta->compileDir, $meta);
    }
}

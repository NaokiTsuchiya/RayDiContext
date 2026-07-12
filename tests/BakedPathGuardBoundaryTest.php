<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\BakedPathFound;
use NaokiTsuchiya\RayDiContext\Fake\Fs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function file_put_contents;
use function mkdir;
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
     */
    protected function setUp(): void
    {
        $this->baseDir = __DIR__ . '/tmp/' . uniqid('guard_boundary_', more_entropy: true);
        $appDir = "{$this->baseDir}/app";
        $this->meta = new AppMeta('fake', $appDir, "{$appDir}/var/di/prod", "{$this->baseDir}/rw-tmp");
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
        $meta = new AppMeta('fake', $this->meta->appDir, $this->meta->compileDir, "{$this->meta->compileDir}/tmp");
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
     * An empty appDir never matches; clean scripts still pass
     *
     * @throws BakedPathFound
     * @throws RuntimeException
     */
    #[Test]
    public function emptyAppDirDoesNotReject(): void
    {
        $meta = new AppMeta('fake', '', $this->meta->compileDir, $this->meta->tmpDir);
        file_put_contents("{$this->meta->compileDir}/clean.php", data: '<?php return new stdClass();');

        ($this->guard)($this->meta->compileDir, $meta);

        $this->expectNotToPerformAssertions();
    }
}

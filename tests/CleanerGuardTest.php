<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\UnsafeCompileDir;
use NaokiTsuchiya\RayDiContext\Fake\FakeRejectingGuard;
use NaokiTsuchiya\RayDiContext\Fake\Fs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function file_put_contents;
use function mkdir;
use function uniqid;

/**
 * The cleaner asks its guard before removing anything
 */
#[CoversClass(Cleaner::class)]
final class CleanerGuardTest extends TestCase
{
    /** @var non-empty-string Per-test working directory */
    private string $baseDir;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->baseDir = __DIR__ . '/tmp/' . uniqid('cleaner_guard_', more_entropy: true);
    }

    /**
     * {@inheritDoc}
     */
    protected function tearDown(): void
    {
        Fs::removeDir($this->baseDir);
    }

    /**
     * The default guard rejects a compile dir holding the app dir, removing nothing
     *
     * @throws RuntimeException
     */
    #[Test]
    public function rejectsCompileDirHoldingAppDirWithoutRemovingAnything(): void
    {
        $appDir = $this->appDirWithFile();
        $meta = new AppMeta('fake', $appDir, $this->baseDir, "{$appDir}/var/tmp");

        try {
            (new Cleaner())($meta);
            static::fail('UnsafeCompileDir was not thrown');
        } catch (UnsafeCompileDir) {
            static::assertFileExists("{$appDir}/keep.php");
        }
    }

    /**
     * An application guard rejecting a compile dir the default one allows is honoured
     *
     * @throws RuntimeException
     */
    #[Test]
    public function honoursApplicationSuppliedGuard(): void
    {
        $appDir = $this->appDirWithFile();
        $compileDir = "{$appDir}/var/di/prod";
        mkdir($compileDir, permissions: 0o755, recursive: true);
        file_put_contents("{$compileDir}/stale.php", data: '<?php return 0;');
        $meta = new AppMeta('fake', $appDir, $compileDir, "{$appDir}/var/tmp");

        try {
            (new Cleaner(new FakeRejectingGuard()))($meta);
            static::fail('UnsafeCompileDir was not thrown');
        } catch (UnsafeCompileDir $e) {
            static::assertStringContainsString('application guard', $e->getMessage());
            static::assertFileExists("{$compileDir}/stale.php");
        }
    }

    /**
     * Creates an app dir holding one file that must survive
     *
     * @return non-empty-string
     */
    private function appDirWithFile(): string
    {
        $appDir = "{$this->baseDir}/app";
        mkdir($appDir, permissions: 0o755, recursive: true);
        file_put_contents("{$appDir}/keep.php", data: '<?php return 0;');

        return $appDir;
    }
}

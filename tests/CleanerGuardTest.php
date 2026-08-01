<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\ExceptionInterface;
use NaokiTsuchiya\RayDiContext\Exception\UnsafeCompileDir;
use NaokiTsuchiya\RayDiContext\Fake\FakeRejectingGuard;
use NaokiTsuchiya\RayDiContext\Fake\Fs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function copy;
use function mkdir;
use function uniqid;

/**
 * The cleaner asks its guard before removing anything
 */
#[CoversClass(Cleaner::class)]
final class CleanerGuardTest extends TestCase
{
    /** Content the tests put on disk and assert survives */
    private const SCRIPT = __DIR__ . '/Fixture/script.php';

    /** @var non-empty-string Per-test working directory */
    private string $baseDir;

    /** @var non-empty-string App dir holding one file that must survive */
    private string $appDir;

    /** @var non-empty-string Conventional compile dir under the app dir, holding one stale script */
    private string $compileDir;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->baseDir = __DIR__ . '/tmp/' . uniqid('cleaner_guard_', more_entropy: true);
        $this->appDir = "{$this->baseDir}/app";
        $this->compileDir = "{$this->appDir}/var/di/prod";
        mkdir($this->compileDir, permissions: 0o755, recursive: true);
        copy(self::SCRIPT, "{$this->appDir}/keep.php");
        copy(self::SCRIPT, "{$this->compileDir}/stale.php");
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
     * @throws ExceptionInterface
     */
    #[Test]
    public function rejectsCompileDirHoldingAppDirWithoutRemovingAnything(): void
    {
        $meta = new AppMeta($this->appDir, 'prod', $this->baseDir, "{$this->appDir}/var/tmp");

        try {
            (new Cleaner())($meta);
            static::fail('UnsafeCompileDir was not thrown');
        } catch (UnsafeCompileDir) {
            static::assertFileExists("{$this->appDir}/keep.php");
        }
    }

    /**
     * An application guard rejecting a compile dir the default one allows is honoured
     *
     * The compile dir here is the conventional one, which the default guard allows, so
     * only the application guard can be what stopped the removal.
     *
     * @throws ExceptionInterface
     */
    #[Test]
    public function honoursApplicationSuppliedGuard(): void
    {
        $meta = new AppMeta($this->appDir, 'prod', $this->compileDir, "{$this->appDir}/var/tmp");

        try {
            (new Cleaner(new FakeRejectingGuard()))($meta);
            static::fail('UnsafeCompileDir was not thrown');
        } catch (UnsafeCompileDir) {
            static::assertFileExists("{$this->compileDir}/stale.php");
        }
    }
}

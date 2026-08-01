<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\CompileDirNotWritable;
use NaokiTsuchiya\RayDiContext\Exception\ExceptionInterface;
use NaokiTsuchiya\RayDiContext\Exception\RemoveFailed;
use NaokiTsuchiya\RayDiContext\Support\Fs;
use NaokiTsuchiya\RayDiContext\Support\PermissionBits;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

use function chmod;
use function copy;
use function file_put_contents;
use function mkdir;
use function uniqid;

/** A compile dir the cleaner cannot create or empty, which it reports without removing anything */
#[CoversClass(Cleaner::class)]
final class CleanerRejectionTest extends TestCase
{
    /** @var non-empty-string Per-test working directory */
    private string $baseDir;

    /** {@inheritDoc} */
    protected function setUp(): void
    {
        $this->baseDir = __DIR__ . '/tmp/' . uniqid('cleaner_reject_', more_entropy: true);
    }

    /** {@inheritDoc} */
    protected function tearDown(): void
    {
        Fs::removeDir($this->baseDir);
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function throwsWhenCompileDirCannotBeCreated(): void
    {
        $blocker = "{$this->baseDir}/blocker";
        mkdir($this->baseDir, permissions: 0o755, recursive: true);
        file_put_contents($blocker, data: '');
        $compileDir = "{$blocker}/di";

        $this->expectException(CompileDirNotWritable::class);
        $this->expectExceptionMessage($compileDir);

        (new Cleaner())($this->meta($compileDir));
    }

    /** @throws ExceptionInterface */
    #[TestWith([0o005], 'cannot list')]
    #[TestWith([0o405], 'cannot traverse')]
    #[Test]
    public function rejectsACompileDirItCannotRead(int $mode): void
    {
        PermissionBits::skipUnlessEnforced($this->baseDir);

        $compileDir = "{$this->baseDir}/di";
        mkdir($compileDir, permissions: 0o700, recursive: true);
        copy(Fs::SCRIPT, "{$compileDir}/stale.php");
        chmod($compileDir, permissions: $mode);

        try {
            (new Cleaner())($this->meta($compileDir));
            static::fail('RemoveFailed was not thrown');
        } catch (RemoveFailed $e) {
            static::assertStringContainsString($compileDir, $e->getMessage());
        } finally {
            chmod($compileDir, permissions: 0o700);
        }

        static::assertFileExists("{$compileDir}/stale.php");
    }

    /** @throws ExceptionInterface */
    #[TestWith([0o005], 'cannot list')]
    #[TestWith([0o405], 'cannot traverse')]
    #[Test]
    public function rejectsANestedDirectoryItCannotRead(int $mode): void
    {
        PermissionBits::skipUnlessEnforced($this->baseDir);

        $compileDir = "{$this->baseDir}/di";
        $nested = "{$compileDir}/nested";
        mkdir($nested, permissions: 0o700, recursive: true);
        copy(Fs::SCRIPT, "{$nested}/stale.php");
        chmod($nested, permissions: $mode);

        try {
            (new Cleaner())($this->meta($compileDir));
            static::fail('RemoveFailed was not thrown');
        } catch (RemoveFailed $e) {
            static::assertStringContainsString($nested, $e->getMessage());
        } finally {
            chmod($nested, permissions: 0o700);
        }

        static::assertFileExists("{$nested}/stale.php");
    }

    /**
     * @param non-empty-string $compileDir
     * @throws ExceptionInterface
     */
    private function meta(string $compileDir): AppMeta
    {
        return new AppMeta("{$this->baseDir}/app", 'prod', $compileDir, "{$this->baseDir}/tmp");
    }
}

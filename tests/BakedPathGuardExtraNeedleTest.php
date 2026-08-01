<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\BakedPathFound;
use NaokiTsuchiya\RayDiContext\Exception\ExceptionInterface;
use NaokiTsuchiya\RayDiContext\Support\Fs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function copy;
use function file_put_contents;
use function mkdir;
use function uniqid;

/**
 * Literals an application adds to the two the guard knows about
 */
#[CoversClass(BakedPathGuard::class)]
final class BakedPathGuardExtraNeedleTest extends TestCase
{
    /** Stands in for whatever an application knows must not reach a shipped script */
    private const CONFIGURED = 'zqx-must-not-ship-4f1c';

    /** A compiled script holding nothing an application configured */
    private const CLEAN_SCRIPT = __DIR__ . '/Fixture/script.php';

    /** @var non-empty-string Per-test working directory */
    private string $baseDir;

    /** Meta whose tmp dir lives outside the app dir */
    private AppMeta $meta;

    /** @throws ExceptionInterface */
    protected function setUp(): void
    {
        $this->baseDir = __DIR__ . '/tmp/' . uniqid('guard_needle_', more_entropy: true);
        $appDir = "{$this->baseDir}/app";
        $this->meta = new AppMeta($appDir, 'prod', "{$appDir}/var/di/prod", "{$this->baseDir}/rw-tmp");
        mkdir($this->meta->compileDir, permissions: 0o755, recursive: true);
    }

    /** {@inheritDoc} */
    protected function tearDown(): void
    {
        Fs::removeDir($this->baseDir);
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function rejectsAConfiguredLiteral(): void
    {
        $script = $this->writeScriptHolding(self::CONFIGURED);

        try {
            (new BakedPathGuard([self::CONFIGURED]))($this->meta);
            static::fail('BakedPathFound was not thrown');
        } catch (BakedPathFound $e) {
            static::assertStringContainsString($script, $e->getMessage());
        }
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function doesNotEchoTheConfiguredLiteral(): void
    {
        $this->writeScriptHolding(self::CONFIGURED);

        try {
            (new BakedPathGuard([self::CONFIGURED]))($this->meta);
            static::fail('BakedPathFound was not thrown');
        } catch (BakedPathFound $e) {
            static::assertStringNotContainsString(self::CONFIGURED, $e->getMessage());
        }
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function passesWhenNoConfiguredLiteralIsPresent(): void
    {
        copy(self::CLEAN_SCRIPT, "{$this->meta->compileDir}/clean.php");

        $this->expectNotToPerformAssertions();

        (new BakedPathGuard([self::CONFIGURED]))($this->meta);
    }

    /** @return non-empty-string */
    private function writeScriptHolding(string $value): string
    {
        $script = "{$this->meta->compileDir}/-db_password.php";
        file_put_contents($script, data: "<?php return '{$value}';");

        return $script;
    }
}

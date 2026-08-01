<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\BakedPathFound;
use NaokiTsuchiya\RayDiContext\Exception\ExceptionInterface;
use NaokiTsuchiya\RayDiContext\Fake\Fs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function copy;
use function file_put_contents;
use function mkdir;
use function uniqid;

/**
 * Literals an application adds to the two the guard knows about
 *
 * The guard itself only knows appDir and tmpDir, yet `toInstance('s3cr3t')` writes the secret
 * into a compiled script all the same. These are how an application names its own.
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

    /**
     * These are supplied precisely because they must not ship, so a message quoting one would
     * move it out of the image and into the CI log rather than keep it out of both.
     *
     * @throws ExceptionInterface
     */
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

        (new BakedPathGuard([self::CONFIGURED]))($this->meta);

        static::assertFileExists("{$this->meta->compileDir}/clean.php");
    }

    /**
     * Writes a compiled script holding $value and returns its path
     *
     * @return non-empty-string
     */
    private function writeScriptHolding(string $value): string
    {
        $script = "{$this->meta->compileDir}/-db_password.php";
        file_put_contents($script, data: "<?php return '{$value}';");

        return $script;
    }
}

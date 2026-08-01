<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\BakedPathFound;
use NaokiTsuchiya\RayDiContext\Exception\ExceptionInterface;
use NaokiTsuchiya\RayDiContext\Support\Fs;
use NaokiTsuchiya\RayDiContext\Support\SeparatedDirFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function copy;
use function file_put_contents;

/**
 * Literals an application adds to the two the guard knows about
 */
#[CoversClass(BakedPathGuard::class)]
final class BakedPathGuardExtraNeedleTest extends TestCase
{
    /** Stands in for whatever an application knows must not reach a shipped script */
    private const CONFIGURED = 'zqx-must-not-ship-4f1c';

    /** Working directory and meta shared by the guard test classes */
    private SeparatedDirFixture $fixture;

    /** Meta whose tmp dir lives outside the app dir */
    private AppMeta $meta;

    /** @throws ExceptionInterface */
    protected function setUp(): void
    {
        $this->fixture = new SeparatedDirFixture('guard_needle_');
        $this->meta = $this->fixture->meta;
    }

    /** {@inheritDoc} */
    protected function tearDown(): void
    {
        $this->fixture->remove();
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
        copy(Fs::SCRIPT, "{$this->meta->compileDir}/clean.php");

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

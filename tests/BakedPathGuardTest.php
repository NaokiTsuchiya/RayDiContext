<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\BakedPathFound;
use NaokiTsuchiya\RayDiContext\Exception\ExceptionInterface;
use NaokiTsuchiya\RayDiContext\Exception\ScriptNotReadable;
use NaokiTsuchiya\RayDiContext\Support\Fs;
use NaokiTsuchiya\RayDiContext\Support\PermissionBits;
use NaokiTsuchiya\RayDiContext\Support\SeparatedDirFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function chmod;
use function copy;
use function file_put_contents;
use function serialize;
use function sprintf;

#[CoversClass(BakedPathGuard::class)]
final class BakedPathGuardTest extends TestCase
{
    /** Working directory and meta shared by the guard test classes */
    private SeparatedDirFixture $fixture;

    /** Meta whose tmp dir lives outside the app dir */
    private AppMeta $meta;

    /** System under test */
    private BakedPathGuard $guard;

    /** @throws ExceptionInterface */
    protected function setUp(): void
    {
        $this->fixture = new SeparatedDirFixture('guard_');
        $this->meta = $this->fixture->meta;
        $this->guard = new BakedPathGuard();
    }

    /** {@inheritDoc} */
    protected function tearDown(): void
    {
        $this->fixture->remove();
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function passesOnCleanScripts(): void
    {
        file_put_contents("{$this->meta->compileDir}/clean.php", data: '<?php return new stdClass();');

        $this->expectNotToPerformAssertions();

        ($this->guard)($this->meta);
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function detectsAppDirLiteral(): void
    {
        file_put_contents("{$this->meta->compileDir}/baked.php", "<?php return '{$this->meta->appDir}/src/Index.php';");

        try {
            ($this->guard)($this->meta);
            static::fail('BakedPathFound was not thrown');
        } catch (BakedPathFound $e) {
            static::assertStringContainsString($this->meta->appDir, $e->getMessage());
            static::assertStringContainsString('baked.php', $e->getMessage());
        }
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function detectsTmpDirLiteral(): void
    {
        $baked = "{$this->meta->compileDir}/baked.php";
        file_put_contents($baked, "<?php return '{$this->meta->tmpDir}/cache';");

        $this->expectException(BakedPathFound::class);
        $this->expectExceptionMessage(sprintf('Baked path "%s" found in %s.', $this->meta->tmpDir, $baked));

        ($this->guard)($this->meta);
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function detectsPathInSerializedInstance(): void
    {
        $serialized = serialize($this->meta);
        $baked = "{$this->meta->compileDir}/baked.php";
        file_put_contents($baked, "<?php return unserialize('{$serialized}');");

        $this->expectException(BakedPathFound::class);
        $this->expectExceptionMessage(sprintf('Baked path "%s" found in %s.', $this->meta->appDir, $baked));

        ($this->guard)($this->meta);
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function allowsCompileDirLiteral(): void
    {
        file_put_contents("{$this->meta->compileDir}/script-dir.php", "<?php return '{$this->meta->compileDir}';");

        $this->expectNotToPerformAssertions();

        ($this->guard)($this->meta);
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function ignoresNonPhpFiles(): void
    {
        file_put_contents("{$this->meta->compileDir}/_bindings.log", "toInstance('{$this->meta->appDir}')");

        $this->expectNotToPerformAssertions();

        ($this->guard)($this->meta);
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function throwsWhenScriptCannotBeRead(): void
    {
        PermissionBits::skipUnlessEnforced($this->fixture->baseDir);

        $unreadable = "{$this->meta->compileDir}/unreadable.php";
        copy(Fs::SCRIPT, $unreadable);
        chmod($unreadable, permissions: 0o000);

        try {
            ($this->guard)($this->meta);
            static::fail('ScriptNotReadable was not thrown');
        } catch (ScriptNotReadable $e) {
            static::assertStringContainsString($unreadable, $e->getMessage());
        } finally {
            chmod($unreadable, permissions: 0o644);
        }
    }
}

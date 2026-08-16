<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\BakedPathFound;
use NaokiTsuchiya\RayDiContext\Exception\CompileDirNotFound;
use NaokiTsuchiya\RayDiContext\Exception\CompileDirNotReadable;
use NaokiTsuchiya\RayDiContext\Exception\ExceptionInterface;
use NaokiTsuchiya\RayDiContext\Exception\InvalidExtraNeedle;
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
use function mkdir;
use function serialize;
use function sprintf;
use function symlink;
use function var_export;

#[CoversClass(BakedPathGuard::class)]
#[CoversClass(InvalidExtraNeedle::class)]
final class BakedPathGuardTest extends TestCase
{
    /** Stands in for whatever an application knows must not reach a shipped script */
    private const CONFIGURED = 'zqx-must-not-ship-4f1c';

    /** The same, holding the two bytes a single-quoted literal escapes; secrets routinely carry both */
    private const CONFIGURED_ESCAPED = 'zqx-must\'not\\ship-4f1c';

    /** Working directory and meta shared by most tests in this class */
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

    /** @throws ExceptionInterface */
    #[Test]
    public function allowsPathInsideCompileDir(): void
    {
        file_put_contents(
            "{$this->meta->compileDir}/script-path.php",
            data: "<?php return '{$this->meta->compileDir}/scripts/x.php';",
        );

        $this->expectNotToPerformAssertions();

        ($this->guard)($this->meta);
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function detectsTmpDirNestedUnderCompileDir(): void
    {
        $meta = new AppMeta(
            $this->meta->appDir,
            $this->meta->context,
            $this->meta->compileDir,
            "{$this->meta->compileDir}/tmp",
        );
        $baked = "{$this->meta->compileDir}/baked.php";
        file_put_contents($baked, data: "<?php return '{$meta->tmpDir}/cache';");

        $this->expectException(BakedPathFound::class);
        $this->expectExceptionMessage(sprintf('Baked path "%s" found in %s.', $meta->tmpDir, $baked));

        ($this->guard)($meta);
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function detectsPathWithCompileDirStringPrefix(): void
    {
        $baked = "{$this->meta->compileDir}/baked.php";
        file_put_contents($baked, data: "<?php return '{$this->meta->compileDir}uction_logs/app.log';");

        $this->expectException(BakedPathFound::class);
        $this->expectExceptionMessage(sprintf('Baked path "%s" found in %s.', $this->meta->appDir, $baked));

        ($this->guard)($this->meta);
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function preservesSymlinkSpellingAgainstBakedPathGuard(): void
    {
        $target = "{$this->fixture->baseDir}/link-target";
        mkdir($target, permissions: 0o755, recursive: true);
        $link = "{$this->fixture->baseDir}/current";
        symlink($target, $link);

        $meta = AppMeta::fromAppDir($link, 'prod');
        static::assertSame($link, $meta->appDir);

        mkdir($meta->compileDir, permissions: 0o755, recursive: true);
        file_put_contents("{$meta->compileDir}/baked.php", "<?php return '{$link}/src/Index.php';");

        $this->expectException(BakedPathFound::class);

        (new BakedPathGuard())($meta);
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function throwsOnSymlinkToDirectoryNamedLikeAScript(): void
    {
        $targetDir = "{$this->fixture->baseDir}/link-target";
        mkdir($targetDir, permissions: 0o755, recursive: true);
        $link = "{$this->meta->compileDir}/cache.php";
        symlink($targetDir, $link);

        $this->expectException(ScriptNotReadable::class);
        $this->expectExceptionMessage($link);

        ($this->guard)($this->meta);
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function scansScriptsInsideADirectoryNamedLikeAScript(): void
    {
        $nestedDir = "{$this->meta->compileDir}/cache.php";
        mkdir($nestedDir, permissions: 0o755, recursive: true);
        $nestedScript = "{$nestedDir}/nested.php";
        file_put_contents($nestedScript, "<?php return '{$this->meta->appDir}/src/Index.php';");

        $this->expectException(BakedPathFound::class);
        $this->expectExceptionMessage(sprintf('Baked path "%s" found in %s.', $this->meta->appDir, $nestedScript));

        ($this->guard)($this->meta);
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

    /** @throws ExceptionInterface */
    #[Test]
    public function rejectsAConfiguredLiteralEscapedInAPlainLiteral(): void
    {
        $script = "{$this->meta->compileDir}/-db_password.php";
        $literal = var_export(self::CONFIGURED_ESCAPED, return: true);
        file_put_contents($script, data: sprintf('<?php return %s;', $literal));

        $this->expectException(BakedPathFound::class);

        (new BakedPathGuard([self::CONFIGURED_ESCAPED]))($this->meta);
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function rejectsAConfiguredLiteralEscapedInASerializedInstance(): void
    {
        $script = "{$this->meta->compileDir}/-db_password.php";
        $blob = var_export(serialize(['password' => self::CONFIGURED_ESCAPED]), return: true);
        file_put_contents($script, data: sprintf('<?php return unserialize(%s);', $blob));

        $this->expectException(BakedPathFound::class);

        (new BakedPathGuard([self::CONFIGURED_ESCAPED]))($this->meta);
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function rejectsAnEmptyNeedleAtConstruction(): void
    {
        $this->expectException(InvalidExtraNeedle::class);

        new BakedPathGuard(['']);
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function rejectsAnEmptyNeedleAmongOthers(): void
    {
        $this->expectException(InvalidExtraNeedle::class);

        new BakedPathGuard([self::CONFIGURED, '']);
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function rejectsANonStringNeedle(): void
    {
        $needle = 42;

        $this->expectException(InvalidExtraNeedle::class);

        new BakedPathGuard([$needle]);
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function invalidNeedleRejectionDoesNotEchoOtherNeedles(): void
    {
        try {
            new BakedPathGuard([self::CONFIGURED, '']);
            static::fail('InvalidExtraNeedle was not thrown');
        } catch (InvalidExtraNeedle $e) {
            static::assertStringNotContainsString(self::CONFIGURED, $e->getMessage());
        }
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function constructsWithNoExtraNeedles(): void
    {
        $this->expectNotToPerformAssertions();

        new BakedPathGuard();
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function constructsWithOnlyNonEmptyNeedles(): void
    {
        $this->expectNotToPerformAssertions();

        new BakedPathGuard([self::CONFIGURED, 'another-needle']);
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function acceptsAZeroStringNeedle(): void
    {
        $this->expectNotToPerformAssertions();

        new BakedPathGuard(['0']);
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function rejectsAMissingCompileDir(): void
    {
        $meta = $this->rejectionMeta();

        try {
            ($this->guard)($meta);
            static::fail('CompileDirNotFound was not thrown');
        } catch (CompileDirNotFound $e) {
            static::assertStringContainsString($meta->compileDir, $e->getMessage());
        }
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function rejectsACompileDirThatIsAFile(): void
    {
        $meta = $this->rejectionMeta();
        mkdir($this->rejectionBase(), permissions: 0o755, recursive: true);
        file_put_contents($meta->compileDir, data: '');

        try {
            ($this->guard)($meta);
            static::fail('CompileDirNotFound was not thrown');
        } catch (CompileDirNotFound $e) {
            static::assertStringContainsString($meta->compileDir, $e->getMessage());
        }
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function rejectsACompileDirItCannotList(): void
    {
        PermissionBits::skipUnlessEnforced($this->fixture->baseDir);

        $meta = $this->rejectionMeta();
        mkdir($meta->compileDir, permissions: 0o700, recursive: true);
        chmod($meta->compileDir, permissions: 0o005);

        try {
            ($this->guard)($meta);
            static::fail('CompileDirNotReadable was not thrown');
        } catch (CompileDirNotReadable $e) {
            static::assertStringContainsString($meta->compileDir, $e->getMessage());
        } finally {
            chmod($meta->compileDir, permissions: 0o700);
        }
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function rejectsACompileDirItCannotTraverse(): void
    {
        PermissionBits::skipUnlessEnforced($this->fixture->baseDir);

        $meta = $this->rejectionMeta();
        mkdir($meta->compileDir, permissions: 0o700, recursive: true);
        file_put_contents("{$meta->compileDir}/a.php", data: '<?php return new stdClass();');
        chmod($meta->compileDir, permissions: 0o405);

        try {
            ($this->guard)($meta);
            static::fail('CompileDirNotReadable was not thrown');
        } catch (CompileDirNotReadable $e) {
            static::assertStringContainsString($meta->compileDir, $e->getMessage());
        } finally {
            chmod($meta->compileDir, permissions: 0o700);
        }
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function rejectsANestedDirectoryItCannotList(): void
    {
        PermissionBits::skipUnlessEnforced($this->fixture->baseDir);

        $meta = $this->rejectionMeta();
        $nested = "{$meta->compileDir}/nested";
        mkdir($nested, permissions: 0o700, recursive: true);
        chmod($nested, permissions: 0o005);

        try {
            ($this->guard)($meta);
            static::fail('CompileDirNotReadable was not thrown');
        } catch (CompileDirNotReadable $e) {
            static::assertStringContainsString($meta->compileDir, $e->getMessage());
        } finally {
            chmod($nested, permissions: 0o700);
        }
    }

    /** @return non-empty-string */
    private function writeScriptHolding(string $value): string
    {
        $script = "{$this->meta->compileDir}/-db_password.php";
        file_put_contents($script, data: "<?php return '{$value}';");

        return $script;
    }

    /**
     * Base dir for the rejection tests, whose compile dir starts out missing —
     * distinct from $this->meta's, which SeparatedDirFixture already creates
     *
     * @return non-empty-string
     */
    private function rejectionBase(): string
    {
        return "{$this->fixture->baseDir}/rejection";
    }

    /** @throws ExceptionInterface */
    private function rejectionMeta(): AppMeta
    {
        $base = $this->rejectionBase();

        return new AppMeta("{$base}/app", 'prod', "{$base}/di", "{$base}/rw-tmp");
    }
}

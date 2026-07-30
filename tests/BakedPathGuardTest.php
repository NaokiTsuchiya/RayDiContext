<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\BakedPathFound;
use NaokiTsuchiya\RayDiContext\Exception\ExceptionInterface;
use NaokiTsuchiya\RayDiContext\Exception\InvalidAppMeta;
use NaokiTsuchiya\RayDiContext\Exception\ScriptNotReadable;
use NaokiTsuchiya\RayDiContext\Fake\Fs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function chmod;
use function copy;
use function file_put_contents;
use function mkdir;
use function serialize;
use function uniqid;

#[CoversClass(BakedPathGuard::class)]
final class BakedPathGuardTest extends TestCase
{
    /** Stands in for a compiled script whose content is irrelevant to the test */
    private const SCRIPT = __DIR__ . '/Fixture/script.php';

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
        $this->baseDir = __DIR__ . '/tmp/' . uniqid('guard_', more_entropy: true);
        $appDir = "{$this->baseDir}/app";
        // The tmp dir is deliberately outside the app dir to exercise both needles
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
     * Scripts free of runtime paths pass
     *
     * @throws BakedPathFound
     * @throws RuntimeException
     */
    #[Test]
    public function passesOnCleanScripts(): void
    {
        file_put_contents("{$this->meta->compileDir}/clean.php", data: '<?php return new stdClass();');

        ($this->guard)($this->meta->compileDir, $this->meta);

        $this->expectNotToPerformAssertions();
    }

    /**
     * An appDir literal in a compiled script is detected, naming the path and the file
     *
     * @throws RuntimeException
     */
    #[Test]
    public function detectsAppDirLiteral(): void
    {
        file_put_contents("{$this->meta->compileDir}/baked.php", "<?php return '{$this->meta->appDir}/src/Index.php';");

        try {
            ($this->guard)($this->meta->compileDir, $this->meta);
            static::fail('BakedPathFound was not thrown');
        } catch (BakedPathFound $e) {
            static::assertStringContainsString($this->meta->appDir, $e->getMessage());
            static::assertStringContainsString('baked.php', $e->getMessage());
        }
    }

    /**
     * A tmpDir literal is detected even when the tmp dir is outside the app dir
     *
     * @throws BakedPathFound
     * @throws RuntimeException
     */
    #[Test]
    public function detectsTmpDirLiteral(): void
    {
        file_put_contents("{$this->meta->compileDir}/baked.php", "<?php return '{$this->meta->tmpDir}/cache';");

        $this->expectException(BakedPathFound::class);

        ($this->guard)($this->meta->compileDir, $this->meta);
    }

    /**
     * A path inside a serialized instance is detected
     *
     * @throws BakedPathFound
     * @throws RuntimeException
     */
    #[Test]
    public function detectsPathInSerializedInstance(): void
    {
        $serialized = serialize($this->meta);
        file_put_contents("{$this->meta->compileDir}/baked.php", "<?php return unserialize('{$serialized}');");

        $this->expectException(BakedPathFound::class);

        ($this->guard)($this->meta->compileDir, $this->meta);
    }

    /**
     * The compile dir itself is baked into the image, so its literal is allowed
     *
     * @throws BakedPathFound
     * @throws RuntimeException
     */
    #[Test]
    public function allowsCompileDirLiteral(): void
    {
        file_put_contents("{$this->meta->compileDir}/script-dir.php", "<?php return '{$this->meta->compileDir}';");

        ($this->guard)($this->meta->compileDir, $this->meta);

        $this->expectNotToPerformAssertions();
    }

    /**
     * Only PHP scripts are scanned; compile artifacts like _bindings.log are ignored
     *
     * @throws BakedPathFound
     * @throws RuntimeException
     */
    #[Test]
    public function ignoresNonPhpFiles(): void
    {
        file_put_contents("{$this->meta->compileDir}/_bindings.log", "toInstance('{$this->meta->appDir}')");

        ($this->guard)($this->meta->compileDir, $this->meta);

        $this->expectNotToPerformAssertions();
    }

    /**
     * A script that is a regular file but cannot be read raises a ScriptNotReadable
     * naming the path
     *
     * is_file() only stats the entry, so it passes for a permission-denied file; the
     * failure has to come from file_get_contents() itself, which is what this exercises.
     * Root ignores the permission, so this only means something under a non-root process.
     *
     * @throws ExceptionInterface
     */
    #[Test]
    public function throwsWhenScriptCannotBeRead(): void
    {
        $unreadable = "{$this->meta->compileDir}/unreadable.php";
        copy(self::SCRIPT, $unreadable);
        chmod($unreadable, permissions: 0o000);

        try {
            ($this->guard)($this->meta->compileDir, $this->meta);
            static::fail('ScriptNotReadable was not thrown');
        } catch (ScriptNotReadable $e) {
            static::assertStringContainsString($unreadable, $e->getMessage());
        } finally {
            chmod($unreadable, permissions: 0o644); // tearDown has to be able to remove it
        }
    }
}

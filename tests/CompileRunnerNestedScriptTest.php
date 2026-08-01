<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use FilesystemIterator;
use NaokiTsuchiya\RayDiContext\Exception\ExceptionInterface;
use NaokiTsuchiya\RayDiContext\Fake\FakeQualifiedContext;
use NaokiTsuchiya\RayDiContext\Support\Fs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function dirname;
use function fileperms;
use function mkdir;
use function uniqid;

/** A compile whose output is not flat is normalized all the way down */
#[CoversClass(CompileRunner::class)]
final class CompileRunnerNestedScriptTest extends TestCase
{
    /** Per-test working directory */
    private string $baseDir;

    /** Meta with conventional paths under the app dir */
    private AppMeta $meta;

    /** @throws ExceptionInterface */
    protected function setUp(): void
    {
        $this->baseDir = __DIR__ . '/tmp/' . uniqid('runner_nested_', more_entropy: true);
        $appDir = "{$this->baseDir}/app";
        mkdir("{$appDir}/var/tmp/prod", permissions: 0o755, recursive: true);
        $this->meta = AppMeta::fromAppDir($appDir, 'prod');
    }

    /** {@inheritDoc} */
    protected function tearDown(): void
    {
        Fs::removeDir($this->baseDir);
    }

    /** @throws ExceptionInterface */
    #[Test]
    public function normalizesAScriptCompiledIntoASubdirectory(): void
    {
        (new CompileRunner(new MapContextProvider(['prod' => FakeQualifiedContext::class])))->run($this->meta);

        $nested = [];
        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->meta->compileDir, FilesystemIterator::SKIP_DOTS),
        );
        /** @var SplFileInfo $entry */
        foreach ($entries as $entry) {
            $pathname = $entry->getPathname();
            $mode = (int) fileperms($pathname) & 0o777;
            $isDir = $entry->isDir();
            $required = $isDir ? 0o005 : 0o004;
            static::assertSame($required, $mode & $required, $pathname);
            $isScript = $entry->getExtension() === 'php';
            if ($isScript) {
                static::assertSame(0o644, $mode, $pathname);
            }

            $parent = dirname($pathname);
            $isNestedScript = $isScript && $parent !== $this->meta->compileDir;
            if (!$isNestedScript) {
                continue;
            }

            $nested[] = $pathname;
        }

        static::assertNotSame([], $nested);
    }
}

<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use FilesystemIterator;
use NaokiTsuchiya\RayDiContext\Exception\ExceptionInterface;
use NaokiTsuchiya\RayDiContext\Fake\FakeQualifiedContext;
use NaokiTsuchiya\RayDiContext\Support\AppDirFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function dirname;
use function fileperms;

/** A compile whose output is not flat is normalized all the way down */
#[CoversClass(CompileRunner::class)]
final class CompileRunnerNestedScriptTest extends TestCase
{
    /** Working directory and meta shared by the compile test classes */
    private AppDirFixture $fixture;

    /** Meta with conventional paths under the app dir */
    private AppMeta $meta;

    /** @throws ExceptionInterface */
    protected function setUp(): void
    {
        $this->fixture = new AppDirFixture('runner_nested_');
        $this->meta = $this->fixture->meta;
    }

    /** {@inheritDoc} */
    protected function tearDown(): void
    {
        $this->fixture->remove();
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

<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext\Fake;

use NaokiTsuchiya\RayDiContext\ScriptCompilerInterface;
use Ray\Di\AbstractModule;

use function file_put_contents;
use function glob;

/**
 * Records what the compile dir held at the moment it was asked to compile
 *
 * Standing in for ray/compiler is what makes the pipeline's ordering observable: whether the
 * cleaner ran first is a fact about the directory when compile() is entered, not something the
 * finished output can be read backwards to prove.
 */
final class FakeRecordingCompiler implements ScriptCompilerInterface
{
    /** @var list<string> Entries present in the compile dir when compile() was entered */
    public array $entriesWhenCalled = [];

    /** Whether compile() was reached at all */
    public bool $called = false;

    /**
     * {@inheritDoc}
     */
    public function compile(AbstractModule $module, string $compileDir): void
    {
        $this->called = true;
        $entries = glob("{$compileDir}/*");
        $this->entriesWhenCalled = $entries === false ? [] : $entries;

        // The guard that runs next needs something to scan, and the normalizer something to chmod
        file_put_contents("{$compileDir}/compiled.php", data: '<?php return 0;');
    }
}

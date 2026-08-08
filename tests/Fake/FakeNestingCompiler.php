<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext\Fake;

use NaokiTsuchiya\RayDiContext\ScriptCompilerInterface;
use Ray\Di\AbstractModule;

use function chmod;
use function file_put_contents;
use function mkdir;

/** Writes one script into a subdirectory of the compile dir, both readable by their owner alone */
final class FakeNestingCompiler implements ScriptCompilerInterface
{
    /** {@inheritDoc} */
    public function compile(AbstractModule $module, string $compileDir): void
    {
        $nested = "{$compileDir}/nested";
        mkdir($nested, permissions: 0o700);
        chmod($nested, permissions: 0o700);

        $script = "{$nested}/compiled.php";
        file_put_contents($script, data: '<?php return 0;');
        chmod($script, permissions: 0o600);
    }
}

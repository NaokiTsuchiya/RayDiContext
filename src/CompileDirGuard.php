<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\UnsafeCompileDir;

use function realpath;
use function str_starts_with;

/**
 * Rejects a compile dir whose contents must never be emptied
 *
 * @api
 */
final class CompileDirGuard implements CompileDirGuardInterface
{
    /** Appended to every rejection: the compile dir is disposable, nothing else is */
    private const HINT = 'Pass a compile dir that holds nothing but compiled scripts.';

    /**
     * {@inheritDoc}
     *
     * @param AppMeta $meta Application metadata carrying the compile dir to verify
     *
     * @throws UnsafeCompileDir When the compile dir is the filesystem root or holds the app dir.
     */
    public function __invoke(AppMeta $meta): void
    {
        $compileDir = $this->canonicalize($meta->compileDir);
        if ($compileDir === '/') {
            throw new UnsafeCompileDir(
                "Refusing to empty the filesystem root as compile dir: {$meta->compileDir}. " . self::HINT,
            );
        }

        $appDir = $this->canonicalize($meta->appDir);
        if ($compileDir === $appDir) {
            throw new UnsafeCompileDir(
                "Refusing to empty the app dir as compile dir: {$meta->compileDir}. " . self::HINT,
            );
        }

        $holdsAppDir = str_starts_with($appDir, "{$compileDir}/");
        if ($holdsAppDir) {
            throw new UnsafeCompileDir(
                "Refusing to empty a compile dir that holds the app dir: {$meta->compileDir} "
                . "would remove {$meta->appDir}. "
                . self::HINT,
            );
        }
    }

    /** Resolves a path, falling back to the path itself when it does not exist */
    private function canonicalize(string $dir): string
    {
        $resolved = realpath($dir);

        return $resolved === false ? $dir : $resolved;
    }
}

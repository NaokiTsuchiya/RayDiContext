<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use function getenv;
use function rtrim;

/**
 * Application metadata with separated compile-time and runtime directories
 *
 * The compileDir holds DI scripts compiled ahead of time. It is baked into the
 * container image and stays read-only at runtime. The tmpDir is a runtime-writable
 * area such as /tmp and must never be baked into the image; resolve it at runtime
 * through a provider, never with toInstance().
 *
 * @api
 */
final readonly class AppMeta
{
    /**
     * @param string           $name       Application name
     * @param string           $appDir     Application root directory
     * @param non-empty-string $compileDir Read-only DI script directory baked into the image
     * @param non-empty-string $tmpDir     Runtime-writable directory, never baked
     */
    public function __construct(
        public string $name,
        public string $appDir,
        public string $compileDir,
        public string $tmpDir,
    ) {}

    /**
     * Creates a meta whose directories default to conventional paths under the app dir
     *
     * The APP_COMPILE_DIR and APP_TMP_DIR environment variables override the defaults,
     * which allows a container deployment to bake the compile dir into the image while
     * pointing the tmp dir at a writable volume. Trailing slashes are trimmed so the
     * paths compare verbatim against baked literals.
     */
    public static function fromAppDir(string $name, string $appDir, string $context): self
    {
        $appDir = rtrim($appDir, characters: '/');

        return new self(
            $name,
            $appDir,
            self::env('APP_COMPILE_DIR', "{$appDir}/var/di/{$context}"),
            self::env('APP_TMP_DIR', "{$appDir}/var/tmp/{$context}"),
        );
    }

    /**
     * Returns the env value, falling back to the default when unset or empty
     *
     * @param non-empty-string $default
     *
     * @return non-empty-string
     */
    private static function env(string $name, string $default): string
    {
        $value = getenv($name);
        if ($value === false) {
            return $default;
        }

        $value = rtrim($value, characters: '/');

        return $value === '' ? $default : $value;
    }
}

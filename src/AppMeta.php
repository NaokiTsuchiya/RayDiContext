<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\InvalidAppMeta;

use function getenv;
use function preg_match;
use function rtrim;
use function sprintf;
use function str_contains;

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
    /** @var non-empty-string Application root directory */
    public string $appDir;

    /** @var non-empty-string Env/context name (e.g. "prod", "dev") */
    public string $context;

    /** @var non-empty-string Read-only DI script directory baked into the image */
    public string $compileDir;

    /** @var non-empty-string Runtime-writable directory, never baked */
    public string $tmpDir;

    /**
     * @param string $appDir     Application root directory
     * @param string $context    Env/context name (e.g. "prod", "dev")
     * @param string $compileDir Read-only DI script directory baked into the image
     * @param string $tmpDir     Runtime-writable directory, never baked
     *
     * @throws InvalidAppMeta When appDir/context/compileDir/tmpDir is empty, or context is not a safe path segment.
     */
    public function __construct(string $appDir, string $context, string $compileDir, string $tmpDir)
    {
        if ($appDir === '') {
            throw new InvalidAppMeta('AppMeta::$appDir must not be empty');
        }

        if ($context === '') {
            throw new InvalidAppMeta('AppMeta::$context must not be empty');
        }

        // $context is interpolated into compileDir/tmpDir path segments (see fromAppDir()),
        // so it is restricted to the same character class BakedPathScanner treats as a path
        // segment. ".." is rejected separately: each "." is individually allowed (e.g. "v1.2"),
        // but the pair is a parent-dir traversal even without a "/".
        $isSafeSegment = preg_match('/\A[A-Za-z0-9_.\-]+\z/', $context) === 1;
        if (!$isSafeSegment || str_contains($context, '..')) {
            throw new InvalidAppMeta(sprintf('AppMeta::$context must be a safe path segment: "%s"', $context));
        }

        if ($compileDir === '') {
            throw new InvalidAppMeta('AppMeta::$compileDir must not be empty');
        }

        if ($tmpDir === '') {
            throw new InvalidAppMeta('AppMeta::$tmpDir must not be empty');
        }

        $this->appDir = self::trimSlash($appDir);
        $this->context = $context;
        $this->compileDir = self::trimSlash($compileDir);
        $this->tmpDir = self::trimSlash($tmpDir);
    }

    /**
     * Trims trailing slashes, keeping a path of only slashes as "/" rather than ""
     *
     * @param non-empty-string $path
     *
     * @return non-empty-string
     */
    private static function trimSlash(string $path): string
    {
        $trimmed = rtrim($path, characters: '/');

        return $trimmed === '' ? '/' : $trimmed;
    }

    /**
     * Creates a meta whose directories default to conventional paths under the app dir
     *
     * The APP_COMPILE_DIR and APP_TMP_DIR environment variables override the defaults,
     * which allows a container deployment to bake the compile dir into the image while
     * pointing the tmp dir at a writable volume. Trailing slashes are trimmed so the
     * paths compare verbatim against baked literals.
     *
     * @throws InvalidAppMeta When appDir/context is empty, or context is not a safe path segment.
     */
    public static function fromAppDir(string $appDir, string $context): self
    {
        $appDir = rtrim($appDir, characters: '/');

        return new self(
            $appDir,
            $context,
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

<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use NaokiTsuchiya\RayDiContext\Exception\InvalidAppMeta;

use function preg_match;
use function rtrim;
use function sprintf;
use function str_starts_with;

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
     * Characters fromAppDir() accepts in $context: it becomes a single path segment of
     * the default compileDir/tmpDir, so this is a whitelist rather than a blacklist of
     * specific dangerous spellings (".", "..", "/", leading/trailing separators, ...) —
     * every one of those is excluded by construction instead of being named individually.
     * "\" is included so a namespaced class-string context (e.g. "App\ProdContext") can
     * be passed through verbatim: unlike "/" and ".", the OS does not resolve "\"
     * specially in a path segment, so it carries none of the collapse risk that excludes
     * "/" and "." here — it is just an ordinary filename character on the POSIX
     * filesystems this package targets.
     */
    private const CONTEXT_PATTERN = '/\A[A-Za-z0-9_\\\\-]+\z/';

    /**
     * @param string $appDir     Application root directory
     * @param string $context    Env/context name (e.g. "prod", "dev")
     * @param string $compileDir Read-only DI script directory baked into the image
     * @param string $tmpDir     Runtime-writable directory, never baked
     *
     * $context carries no character restriction here: it is only a lookup key (e.g. for
     * MapContextProvider), not necessarily a path fragment. fromAppDir() is the entry
     * point that interpolates it into a path, and validates it as a safe segment there.
     *
     * @throws InvalidAppMeta When appDir/context/compileDir/tmpDir is empty.
     */
    public function __construct(string $appDir, string $context, string $compileDir, string $tmpDir)
    {
        if ($appDir === '') {
            throw new InvalidAppMeta('AppMeta::$appDir must not be empty');
        }

        if ($context === '') {
            throw new InvalidAppMeta('AppMeta::$context must not be empty');
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
     * $compileDir/$tmpDir default to "{appDir}/var/di/{context}" and
     * "{appDir}/var/tmp/{context}" when omitted; pass explicit values (e.g. read from
     * APP_COMPILE_DIR/APP_TMP_DIR by the caller) to override, which lets a container
     * deployment bake the compile dir into the image while pointing the tmp dir at a
     * writable volume. This method does not read the environment itself. Trailing
     * slashes are trimmed so the paths compare verbatim against baked literals.
     *
     * $context is interpolated into the default compileDir/tmpDir here as a single path
     * segment, so it is restricted to CONTEXT_PATTERN's alphabet (letters, digits, "_",
     * "-", "\") rather than validated against a growing list of dangerous spellings. Both
     * "." and "/" are excluded by that restriction: either one, alone or as part of a
     * leading/trailing/doubled separator, would otherwise make compileDir resolve to
     * "{appDir}/var/di" itself — the parent shared by every context — so Cleaner emptying
     * it would delete every other context's compiled scripts, not just this one's.
     *
     * $appDir must already be absolute; it is rejected otherwise rather than resolved.
     * BakedPathGuard compares meta strings verbatim against literals frozen into compiled
     * scripts, so whatever spelling is bound at compile time has to be the same spelling
     * the running app binds — resolving symlinks or "." segments here would silently
     * change that spelling and make the guard fail open. A relative appDir is rejected
     * outright rather than resolved against the working directory: left as-is it would
     * reach BakedPathGuard as a needle that matches nearly every literal — "." matches
     * all of them — and fail the compile with a message that reads as a baked path rather
     * than as a bad argument. Whether appDir exists on disk is a caller concern (e.g.
     * bin/ray-di-compile checks it as a usage error); this factory only checks its shape.
     *
     * @throws InvalidAppMeta When appDir is not absolute or empty, or context does not
     *                        match CONTEXT_PATTERN (letters, digits, "_", "-", "\" only).
     */
    public static function fromAppDir(
        string $appDir,
        string $context,
        ?string $compileDir = null,
        ?string $tmpDir = null,
    ): self {
        $isSafeContext = preg_match(self::CONTEXT_PATTERN, $context) === 1;
        if (!$isSafeContext) {
            throw new InvalidAppMeta(sprintf(
                'AppMeta::fromAppDir(): $context must contain only letters, digits, "_", "-", or "\\": "%s"',
                $context,
            ));
        }

        if ($appDir === '') {
            throw new InvalidAppMeta('AppMeta::fromAppDir(): $appDir must not be empty');
        }

        if (!str_starts_with($appDir, '/')) {
            throw new InvalidAppMeta(sprintf('AppMeta::fromAppDir(): $appDir must be an absolute path: "%s"', $appDir));
        }

        // Trimmed ahead of interpolation below so a trailing slash on $appDir does not
        // leave a doubled slash in the default compileDir/tmpDir.
        $appDir = self::trimSlash($appDir);

        return new self(
            $appDir,
            $context,
            $compileDir ?? "{$appDir}/var/di/{$context}",
            $tmpDir ?? "{$appDir}/var/tmp/{$context}",
        );
    }
}

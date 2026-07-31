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
     * @param string $appDir     Application root directory; must be absolute — enforced
     *                           here rather than only in fromAppDir(), since
     *                           BakedPathGuard/CompileDirGuard read it verbatim regardless
     *                           of which entry point produced it
     * @param string $context    Env/context name (e.g. "prod", "dev"); only a lookup key
     *                           here (e.g. for MapContextProvider), not a path fragment —
     *                           fromAppDir() validates it as a safe path segment instead
     * @param string $compileDir Read-only DI script directory baked into the image
     * @param string $tmpDir     Runtime-writable directory, never baked
     *
     * @throws InvalidAppMeta When appDir/context/compileDir/tmpDir is empty, appDir is not an
     *                        absolute path, or compileDir and tmpDir are the same directory.
     */
    public function __construct(string $appDir, string $context, string $compileDir, string $tmpDir)
    {
        if ($appDir === '') {
            throw new InvalidAppMeta('AppMeta::$appDir must not be empty');
        }

        if (!str_starts_with($appDir, '/')) {
            throw new InvalidAppMeta(sprintf('AppMeta::$appDir must be an absolute path: "%s"', $appDir));
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

        $normalizedCompileDir = self::trimSlash($compileDir);
        $normalizedTmpDir = self::trimSlash($tmpDir);

        // Rejected because it is the one shape BakedPathGuard cannot see. The guard allows a
        // literal that lies inside a compileDir literal — the compile dir is baked in with the
        // scripts, so that is legitimate — and reports the tmpDir otherwise. When the two are
        // the same string, every tmpDir occurrence is exactly a compileDir occurrence, so the
        // tmpDir check silently passes on scripts it exists to reject. A tmpDir merely nested
        // under the compile dir extends past the allowed literal and is still caught, so it is
        // left to the guard rather than refused here.
        if ($normalizedCompileDir === $normalizedTmpDir) {
            throw new InvalidAppMeta(sprintf(
                'AppMeta::$compileDir and AppMeta::$tmpDir must be different directories, both are: "%s". '
                . 'The compile dir is read-only at runtime and cannot host the writable tmp dir.',
                $normalizedCompileDir,
            ));
        }

        $this->appDir = self::trimSlash($appDir);
        $this->context = $context;
        $this->compileDir = $normalizedCompileDir;
        $this->tmpDir = $normalizedTmpDir;
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
     * @param string      $appDir     Application root directory; must be absolute, as
     *                                enforced by the constructor (see __construct())
     * @param string      $context    Env/context name; must match CONTEXT_PATTERN (see
     *                                that constant's doc) since it becomes a path segment
     *                                of the defaults below
     * @param string|null $compileDir Read-only DI script directory baked into the image;
     *                                defaults to "{appDir}/var/di/{context}"
     * @param string|null $tmpDir     Runtime-writable directory, never baked; defaults to
     *                                "{appDir}/var/tmp/{context}"
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

        // Checked here, ahead of trimSlash(), because trimSlash() folds an empty string
        // to "/" — which would pass the constructor's absolute-path check before it ever
        // saw $appDir was empty.
        if ($appDir === '') {
            throw new InvalidAppMeta('AppMeta::fromAppDir(): $appDir must not be empty');
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

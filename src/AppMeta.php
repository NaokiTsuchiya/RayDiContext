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
     * Characters fromAppDir() accepts in $context, which becomes one path segment of the
     * default compileDir/tmpDir
     */
    private const CONTEXT_PATTERN = '/\A[A-Za-z0-9_\\\\-]+\z/';

    /**
     * @param string $appDir     Application root directory; must be absolute — the guards read
     *                           it verbatim whichever entry point produced it
     * @param string $context    Env/context name; only a lookup key here, not a path fragment —
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
     * @param string      $appDir     Application root directory; must be absolute
     * @param string      $context    Env/context name; must match CONTEXT_PATTERN, since it
     *                                becomes a path segment of the defaults below
     * @param string|null $compileDir Defaults to "{appDir}/var/di/{context}"
     * @param string|null $tmpDir     Defaults to "{appDir}/var/tmp/{context}"
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

        $appDir = self::trimSlash($appDir);

        return new self(
            $appDir,
            $context,
            $compileDir ?? "{$appDir}/var/di/{$context}",
            $tmpDir ?? "{$appDir}/var/tmp/{$context}",
        );
    }
}

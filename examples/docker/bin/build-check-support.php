<?php

declare(strict_types=1);

/**
 * Pure helpers for examples/docker/bin/build-check's preload.php generation. Split out so
 * filterCompileDirFiles()/relativePath() are testable without a real compile or Docker — see
 * tests/docker-check.sh.
 */

/**
 * @param list<string> $files Absolute file paths, e.g. from ReflectionClass::getFileName()
 *
 * @return list<string> The subset of $files that live under $compileDir
 */
function filterCompileDirFiles(string $compileDir, array $files): array
{
    $real = realpath($compileDir);
    if ($real === false) {
        return [];
    }

    $prefix = $real . DIRECTORY_SEPARATOR;

    return array_values(array_filter(
        $files,
        static fn (string $file): bool => str_starts_with($file, $prefix),
    ));
}

/** Relative path from directory $from to file $to, walking up with "../" as needed */
function relativePath(string $from, string $to): string
{
    $fromParts = explode(DIRECTORY_SEPARATOR, rtrim($from, DIRECTORY_SEPARATOR));
    $toParts = explode(DIRECTORY_SEPARATOR, rtrim($to, DIRECTORY_SEPARATOR));

    while ($fromParts !== [] && $toParts !== [] && $fromParts[0] === $toParts[0]) {
        array_shift($fromParts);
        array_shift($toParts);
    }

    return str_repeat('..' . DIRECTORY_SEPARATOR, count($fromParts)) . implode(DIRECTORY_SEPARATOR, $toParts);
}

/**
 * Writes appDir/preload.php, require_once-ing (relative to __DIR__) every loaded class whose
 * file lives under compileDir — the AOP proxies CompiledInjector's own autoloader materializes
 * there. Composer's classmap already covers everything else the autoload spy could have seen, so
 * those are left out rather than duplicated.
 *
 * @param list<class-string> $loadedClasses Classes recorded by the autoload spy this run
 *
 * @throws RuntimeException When appDir/preload.php cannot be written.
 */
function writePreload(string $compileDir, string $appDir, array $loadedClasses): void
{
    $files = [];
    foreach (array_unique($loadedClasses) as $class) {
        if (!class_exists($class, false) && !interface_exists($class, false)) {
            continue;
        }

        $file = (new ReflectionClass($class))->getFileName();
        if ($file !== false) {
            $files[] = $file;
        }
    }

    $kept = filterCompileDirFiles($compileDir, $files);

    $lines = array_map(
        static fn (string $file): string => sprintf(
            "require_once __DIR__ . %s;\n",
            var_export(DIRECTORY_SEPARATOR . relativePath($appDir, $file), true),
        ),
        $kept,
    );

    $target = $appDir . '/preload.php';
    $written = file_put_contents($target, "<?php\n\ndeclare(strict_types=1);\n\n" . implode('', $lines));
    if ($written === false) {
        throw new RuntimeException(sprintf('Failed to write "%s"', $target));
    }
}

<?php

declare(strict_types=1);

/**
 * Confirms that `composer show --direct`'s resolved ray/di and ray/compiler versions equal the
 * lower bound named by composer.json's own caret constraint (^X.Y or ^X.Y.Z) — the assertion the
 * `lowest` CI job needs so a `require` change that stops touching either package, or a missing
 * --prefer-lowest, cannot leave the job silently testing the newest release instead of the
 * declared floor. Invoked by `composer lowest-check`, after the --prefer-lowest update step, with
 * the working directory Composer always uses for scripts: the directory holding composer.json.
 */

/**
 * Derives a caret constraint's own lower bound (^X.Y -> X.Y.0, ^X.Y.Z -> X.Y.Z) without
 * hardcoding a version; any other constraint shape is refused rather than guessed at.
 */
function lowerBoundOf(string $constraint): ?string
{
    if (preg_match('/^\^(\d+)\.(\d+)(?:\.(\d+))?$/', $constraint, $matches) !== 1) {
        return null;
    }

    return sprintf('%s.%s.%s', $matches[1], $matches[2], $matches[3] ?? '0');
}

function fail(string $message): never
{
    fwrite(STDERR, "lowest-bound-check: {$message}\n");

    exit(1);
}

$composerJson = json_decode((string) file_get_contents('composer.json'), true, flags: JSON_THROW_ON_ERROR);

$installedOutput = shell_exec('composer show --direct --format=json');
if (!is_string($installedOutput)) {
    fail('composer show --direct --format=json produced no output');
}

$installed = json_decode($installedOutput, true, flags: JSON_THROW_ON_ERROR);

foreach (['ray/di', 'ray/compiler'] as $package) {
    $constraint = $composerJson['require'][$package] ?? null;
    if ($constraint === null) {
        fail("{$package} is not declared in composer.json's require");
    }

    $expected = lowerBoundOf($constraint);
    if ($expected === null) {
        fail("composer.json's {$package} constraint '{$constraint}' is not a bare caret range (^X.Y or ^X.Y.Z); cannot derive its lower bound without hardcoding it");
    }

    $actual = null;
    foreach ($installed['installed'] as $entry) {
        if ($entry['name'] === $package) {
            $actual = $entry['version'];

            break;
        }
    }

    if ($actual === null) {
        fail("{$package} is not among the installed direct dependencies (composer show --direct)");
    }

    if ($actual !== $expected) {
        fail("{$package} resolved to {$actual}, expected the declared lower bound {$expected} (from composer.json constraint {$constraint})");
    }

    fwrite(STDOUT, "lowest-bound-check: OK — {$package} resolved to its declared lower bound {$expected}\n");
}

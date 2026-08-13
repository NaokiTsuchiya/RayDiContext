<?php

declare(strict_types=1);

/**
 * Synthetic check of .github/scripts/ci-status.php's assertCiConclusionSuccess() — invoked by
 * tests/ci-status-check.sh as `php ci-status-check-probe.php <support file>`. Exercises the
 * branches a real green release run never reaches: a non-"success" conclusion and a missing
 * check run.
 */

require $argv[1];

assertCiConclusionSuccess('success');

$failureMessage = null;
try {
    assertCiConclusionSuccess('failure');
} catch (RuntimeException $e) {
    $failureMessage = $e->getMessage();
}

if ($failureMessage === null || !str_contains($failureMessage, '"failure"')) {
    fwrite(STDERR, sprintf(
        "assertCiConclusionSuccess: expected a RuntimeException naming \"failure\", got %s\n",
        $failureMessage === null ? 'none thrown' : $failureMessage,
    ));

    exit(1);
}

$notFoundMessage = null;
try {
    assertCiConclusionSuccess(null);
} catch (RuntimeException $e) {
    $notFoundMessage = $e->getMessage();
}

if ($notFoundMessage === null || !str_contains($notFoundMessage, '<not found>')) {
    fwrite(STDERR, sprintf(
        "assertCiConclusionSuccess: expected a RuntimeException naming <not found>, got %s\n",
        $notFoundMessage === null ? 'none thrown' : $notFoundMessage,
    ));

    exit(1);
}

exit(0);

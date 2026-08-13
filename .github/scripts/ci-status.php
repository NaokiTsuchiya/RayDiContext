<?php

declare(strict_types=1);

/**
 * Pure gate for .github/workflows/tag-release.yml's validate job — see tests/ci-status-check.sh.
 */

/**
 * @throws RuntimeException When $conclusion is not "success" ($conclusion === null means no
 *     check run named "ci" was found for the commit).
 */
function assertCiConclusionSuccess(?string $conclusion): void
{
    if ($conclusion !== 'success') {
        throw new RuntimeException(sprintf(
            'ci job conclusion is %s, not success',
            $conclusion === null ? '<not found>' : sprintf('"%s"', $conclusion),
        ));
    }
}

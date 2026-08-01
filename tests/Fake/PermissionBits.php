<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext\Fake;

use PHPUnit\Framework\TestCase;

use function chmod;
use function mkdir;
use function restore_error_handler;
use function rmdir;
use function scandir;
use function set_error_handler;
use function uniqid;

/**
 * Whether this process is actually denied by the permission bits it sets
 */
final class PermissionBits
{
    /** @param non-empty-string $scratchDir Writable directory to probe below; created if absent */
    public static function skipUnlessEnforced(string $scratchDir): void
    {
        $enforced = self::areEnforced($scratchDir);
        if ($enforced) {
            return;
        }

        TestCase::markTestSkipped(
            'This process is not denied by the permission bits it sets — running as root, or '
            . 'holding CAP_DAC_OVERRIDE, or on a filesystem that does not enforce modes. A test '
            . 'that asserts a denial cannot say anything here. CI runs as a non-root user.',
        );
    }

    /** @param non-empty-string $scratchDir Writable directory to probe below; created if absent */
    public static function areEnforced(string $scratchDir): bool
    {
        $probe = $scratchDir . '/' . uniqid('.permission-probe_', more_entropy: true);

        set_error_handler(static fn(): bool => true);
        try {
            mkdir($probe, permissions: 0o700, recursive: true);
            chmod($probe, permissions: 0o000);
            $listed = scandir($probe);
            chmod($probe, permissions: 0o700);
            rmdir($probe);
        } finally {
            restore_error_handler();
        }

        return $listed === false;
    }
}

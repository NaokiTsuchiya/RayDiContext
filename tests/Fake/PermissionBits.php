<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext\Fake;

use function chmod;
use function mkdir;
use function restore_error_handler;
use function rmdir;
use function scandir;
use function set_error_handler;
use function uniqid;

/**
 * Whether this process is actually denied by the permission bits it sets
 *
 * Several tests make a directory unreadable and assert that the package reports it. Run as root
 * none of that holds — CAP_DAC_OVERRIDE and CAP_DAC_READ_SEARCH mean the mode is set and then
 * ignored — so those tests fail with "… was not thrown" and nothing in the message says why.
 * CI runs as a non-root user deliberately; a container shell usually does not.
 *
 * The capability is measured rather than inferred from the uid. Reading the uid would need
 * ext-posix, a dependency this package deliberately does not declare, and would still be a
 * proxy: a non-root process holding CAP_DAC_OVERRIDE, or a filesystem that does not enforce
 * modes at all, is denied nothing either.
 */
final class PermissionBits
{
    /**
     * Returns whether a directory this process owns can be made unreadable to it
     *
     * @param non-empty-string $scratchDir Writable directory to probe below; created if absent
     */
    public static function areEnforced(string $scratchDir): bool
    {
        $probe = $scratchDir . '/' . uniqid('.permission-probe_', more_entropy: true);

        // scandir() on an unreadable directory raises E_WARNING, and the suite fails on warnings
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

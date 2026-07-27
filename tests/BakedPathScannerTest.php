<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Boundary matrix for the scanner, driven straight by (script, compileDir, needle) triples
 *
 * BakedPathGuard covers the same class through the filesystem, where one more boundary case
 * costs a directory and a file. These cases are the boundary itself, so they are spelled out
 * here as data instead.
 */
#[CoversClass(BakedPathScanner::class)]
final class BakedPathScannerTest extends TestCase
{
    /** The conventional compile dir the cases below are scanned against */
    private const COMPILE_DIR = '/app/var/di/prod';

    /**
     * A needle counts only where it spans whole path segments, and only outside the compile dir
     *
     * @param string           $script     Contents of one compiled script
     * @param string           $compileDir The baked, read-only compile dir
     * @param non-empty-string $needle     The runtime path that must not be baked
     * @param bool             $expected   Whether the needle is reported as baked
     */
    #[DataProvider('cases')]
    #[Test]
    public function hasBakedPath(string $script, string $compileDir, string $needle, bool $expected): void
    {
        $scanner = new BakedPathScanner($script, $compileDir);

        static::assertSame($expected, $scanner->hasBakedPath($needle));
    }

    /**
     * @return iterable<string, array{string, string, non-empty-string, bool}>
     */
    public static function cases(): iterable
    {
        // Detected: the needle names a path of its own.
        yield 'needle followed by a separator' => ["<?php return '/app/var/log';", self::COMPILE_DIR, '/app', true];
        yield 'needle alone in a literal' => ["<?php return '/app';", self::COMPILE_DIR, '/app', true];
        yield 'needle at the start of the script' => ['/app/var/log', self::COMPILE_DIR, '/app', true];
        yield 'needle at the end of the script' => ['<?php // see /app', self::COMPILE_DIR, '/app', true];
        yield 'tmp dir needle' => ["<?php return '/tmp/cache/di';", self::COMPILE_DIR, '/tmp', true];
        yield 'needle twice, allowed once' => [
            "<?php return ['/app/var/di/prod/a.php', '/app/etc'];",
            self::COMPILE_DIR,
            '/app',
            true,
        ];

        // A multi-byte character is not in the ASCII segment class, so it reads as a boundary
        // and the occurrence is reported. Fail-close, like the guard around it.
        yield 'multi-byte character after the needle' => [
            "<?php return '/appの/var';",
            self::COMPILE_DIR,
            '/app',
            true,
        ];

        // Not detected: a segment character on either side runs the match into a longer
        // segment, which is a different path.
        yield 'letter after the needle' => ["<?php return '/appdata/config.php';", self::COMPILE_DIR, '/app', false];
        yield 'word continuing the needle' => ["<?php return '/application/config';", self::COMPILE_DIR, '/app', false];
        yield 'digit after the needle' => ["<?php return '/app2/var';", self::COMPILE_DIR, '/app', false];
        yield 'underscore after the needle' => ["<?php return '/app_old/var';", self::COMPILE_DIR, '/app', false];
        yield 'dot after the needle' => ["<?php return '/app.bak/var';", self::COMPILE_DIR, '/app', false];
        yield 'hyphen after the needle' => ["<?php return '/app-old/var';", self::COMPILE_DIR, '/app', false];
        yield 'tmp dir needle inside a longer segment' => [
            "<?php return '/srv/tmpl/index.tpl';",
            self::COMPILE_DIR,
            '/tmp',
            false,
        ];
        yield 'letter before the needle' => [
            "<?php return '/var/backup/app/config';",
            self::COMPILE_DIR,
            '/app',
            false,
        ];

        // Case-sensitive, like the verbatim comparison the scanner is part of. A case-folding
        // filesystem can reach the same directory through both spellings, but the compiled
        // literal is only ever the one the meta carries.
        yield 'needle differing in case' => ["<?php return '/App/src/Index.php';", self::COMPILE_DIR, '/app', false];

        // The compile dir is baked into the image with the scripts, so it and anything under
        // it is allowed.
        yield 'path inside the compile dir' => [
            "<?php return '/app/var/di/prod/scripts/x.php';",
            self::COMPILE_DIR,
            '/app',
            false,
        ];
        yield 'the compile dir itself' => ["<?php return '/app/var/di/prod';", self::COMPILE_DIR, '/app', false];

        // The half-open [start, end) range has to include a needle that fills it exactly:
        // an explicit compileDir override may be the appDir itself.
        yield 'needle equal to the compile dir' => [
            "<?php return '/app/var/di/prod';",
            self::COMPILE_DIR,
            self::COMPILE_DIR,
            false,
        ];

        // Every compile dir literal is collected, not just the first.
        yield 'two compile dir literals' => [
            "<?php return ['/app/var/di/prod/a.php', '/app/var/di/prod/b.php'];",
            self::COMPILE_DIR,
            '/app',
            false,
        ];

        // A tmp dir under the read-only compile dir extends past the literal, so it stays
        // detected: the compile dir can never host it.
        yield 'tmp dir nested under the compile dir' => [
            "<?php return '/app/var/di/prod/tmp/cache';",
            self::COMPILE_DIR,
            '/app/var/di/prod/tmp',
            true,
        ];

        // A sibling merely prefixed by the compile dir string is no compile dir literal, so
        // the appDir inside it is not covered by an allowed range.
        yield 'path with the compile dir as a string prefix' => [
            "<?php return '/app/var/di/production_logs/app.log';",
            self::COMPILE_DIR,
            '/app',
            true,
        ];

        // ...and neither is a path that merely ends with it. Only a relative compile dir can
        // reach this: under an absolute one, every needle position other than its own start
        // is preceded by a segment character and is dropped on the needle side already.
        yield 'path with the compile dir as a string suffix' => [
            "<?php return '/srv/predeploy/app/var/di/prod/x.php';",
            'deploy/app/var/di/prod',
            'app',
            true,
        ];
    }
}

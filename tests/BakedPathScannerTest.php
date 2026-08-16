<?php

declare(strict_types=1);

namespace NaokiTsuchiya\RayDiContext;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Boundary matrix for the scanner, driven straight by (script, compileDir, needle) triples
 */
#[CoversClass(BakedPathScanner::class)]
final class BakedPathScannerTest extends TestCase
{
    /** The conventional compile dir the cases below are scanned against */
    private const COMPILE_DIR = '/app/var/di/prod';

    /**
     * @param non-empty-string $compileDir
     * @param non-empty-string $needle
     */
    #[DataProvider('bakedCases')]
    #[Test]
    public function detectsBakedPath(string $script, string $compileDir, string $needle): void
    {
        $scanner = new BakedPathScanner($script, $compileDir);

        static::assertTrue($scanner->hasBakedPath($needle));
    }

    /**
     * @param non-empty-string $compileDir
     * @param non-empty-string $needle
     */
    #[DataProvider('allowedCases')]
    #[Test]
    public function allowsPath(string $script, string $compileDir, string $needle): void
    {
        $scanner = new BakedPathScanner($script, $compileDir);

        static::assertFalse($scanner->hasBakedPath($needle));
    }

    /** @return iterable<string, array{string, non-empty-string, non-empty-string}> */
    public static function bakedCases(): iterable
    {
        yield 'needle followed by a separator' => ["<?php return '/app/var/log';", self::COMPILE_DIR, '/app'];
        yield 'needle alone in a literal' => ["<?php return '/app';", self::COMPILE_DIR, '/app'];
        yield 'needle at the start of the script' => ['/app/var/log', self::COMPILE_DIR, '/app'];
        yield 'needle at the end of the script' => ['<?php // see /app', self::COMPILE_DIR, '/app'];
        yield 'tmp dir needle' => ["<?php return '/tmp/cache/di';", self::COMPILE_DIR, '/tmp'];
        yield 'needle twice, allowed once' => [
            "<?php return ['/app/var/di/prod/a.php', '/app/etc'];",
            self::COMPILE_DIR,
            '/app',
        ];

        yield 'multi-byte character after the needle' => ["<?php return '/appの/var';", self::COMPILE_DIR, '/app'];

        yield 'tmp dir nested under the compile dir' => [
            "<?php return '/app/var/di/prod/tmp/cache';",
            self::COMPILE_DIR,
            '/app/var/di/prod/tmp',
        ];

        yield 'path with the compile dir as a string prefix' => [
            "<?php return '/app/var/di/production_logs/app.log';",
            self::COMPILE_DIR,
            '/app',
        ];

        yield 'path with the compile dir as a string suffix' => [
            "<?php return '/srv/predeploy/app/var/di/prod/x.php';",
            'deploy/app/var/di/prod',
            'app',
        ];

        yield 'needle whose quote var_export escaped in a serialized blob' => [
            "<?php return unserialize('a:1:{s:6:\"appDir\";s:11:\"/app/qu\\'ote\";}');",
            self::COMPILE_DIR,
            "/app/qu'ote",
        ];

        yield 'needle whose backslash var_export escaped in a serialized blob' => [
            "<?php return unserialize('a:1:{s:6:\"appDir\";s:15:\"/app/back\\\\slash\";}');",
            self::COMPILE_DIR,
            '/app/back\slash',
        ];

        yield 'needle escaped in a plain literal argument' => [
            "<?php return new stdClass('/app/qu\\'ote/src');",
            self::COMPILE_DIR,
            "/app/qu'ote",
        ];

        yield 'quote after the needle, a byte a delimiter cannot be told from' => [
            "<?php return '/app\\'cache/config';",
            self::COMPILE_DIR,
            '/app',
        ];
    }

    /** @return iterable<string, array{string, non-empty-string, non-empty-string}> */
    public static function allowedCases(): iterable
    {
        yield 'letter after the needle' => ["<?php return '/appdata/config.php';", self::COMPILE_DIR, '/app'];
        yield 'backslash after the needle' => [
            "<?php return '/app\\\\cache/config';",
            self::COMPILE_DIR,
            '/app',
        ];
        yield 'backslash before the needle' => [
            "<?php return '/srv/data\\\\/app/var';",
            self::COMPILE_DIR,
            '/app',
        ];
        yield 'word continuing the needle' => ["<?php return '/application/config';", self::COMPILE_DIR, '/app'];
        yield 'digit after the needle' => ["<?php return '/app2/var';", self::COMPILE_DIR, '/app'];
        yield 'underscore after the needle' => ["<?php return '/app_old/var';", self::COMPILE_DIR, '/app'];
        yield 'dot after the needle' => ["<?php return '/app.bak/var';", self::COMPILE_DIR, '/app'];
        yield 'hyphen after the needle' => ["<?php return '/app-old/var';", self::COMPILE_DIR, '/app'];
        yield 'tmp dir needle inside a longer segment' => [
            "<?php return '/srv/tmpl/index.tpl';",
            self::COMPILE_DIR,
            '/tmp',
        ];
        yield 'letter before the needle' => ["<?php return '/var/backup/app/config';", self::COMPILE_DIR, '/app'];
        yield 'segment char forms the very first byte of the script' => ['p/app/var', self::COMPILE_DIR, '/app'];

        yield 'needle differing in case' => ["<?php return '/App/src/Index.php';", self::COMPILE_DIR, '/app'];

        yield 'path inside the compile dir' => [
            "<?php return '/app/var/di/prod/scripts/x.php';",
            self::COMPILE_DIR,
            '/app',
        ];
        yield 'the compile dir itself' => ["<?php return '/app/var/di/prod';", self::COMPILE_DIR, '/app'];

        yield 'needle equal to the compile dir' => [
            "<?php return '/app/var/di/prod';",
            self::COMPILE_DIR,
            self::COMPILE_DIR,
        ];

        yield 'two compile dir literals' => [
            "<?php return ['/app/var/di/prod/a.php', '/app/var/di/prod/b.php'];",
            self::COMPILE_DIR,
            '/app',
        ];

        yield 'needle inside an escaped compile dir literal' => [
            "<?php return '/app/qu\\'ote/di/scripts/x.php';",
            "/app/qu'ote/di",
            '/app',
        ];
    }
}

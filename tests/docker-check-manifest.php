<?php

declare(strict_types=1);

/**
 * Injects a path repository into examples/docker/composer.json while preserving its require constraint.
 */
if (!isset($argv[1], $argv[2])) {
    fwrite(STDERR, "Usage: php docker-check-manifest.php <path> <version>\n");

    exit(1);
}

$path = $argv[1];
$packageVersion = $argv[2];

$contents = file_get_contents($path);
if ($contents === false) {
    fwrite(STDERR, "Could not read {$path}\n");

    exit(1);
}

/** @var array<string, mixed> $manifest */
$manifest = json_decode($contents, associative: true, flags: JSON_THROW_ON_ERROR);
$manifest['repositories'] = [
    [
        'type' => 'path',
        'url' => './package',
        'options' => [
            'symlink' => false,
            'versions' => [
                'naoki-tsuchiya/ray-di-context' => $packageVersion,
            ],
        ],
    ],
];

file_put_contents($path, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

<?php

it('has no remaining config reads for dashed-marketing.channels in src', function () {
    $srcPath = realpath(__DIR__ . '/../../src');

    $offenders = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($srcPath, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $contents = file_get_contents($file->getPathname());
        if (str_contains($contents, 'dashed-marketing.channels')) {
            $offenders[] = str_replace($srcPath . DIRECTORY_SEPARATOR, '', $file->getPathname());
        }
    }

    sort($offenders);

    expect($offenders)->toBe([]);
});

<?php

use Dashed\DashedMarketing\Services\ArticleSanitizer;

it('replaces em dashes with commas', function () {
    $sanitizer = new ArticleSanitizer();
    $out = $sanitizer->sanitize('Dit is een test - met een em dash.');
    expect($out)->toBe('Dit is een test, met een em dash.');
});

it('replaces en dashes with commas', function () {
    $sanitizer = new ArticleSanitizer();
    $out = $sanitizer->sanitize('Dit is – ook – een test.');
    expect($out)->toBe('Dit is, ook, een test.');
});

it('normalises curly apostrophes', function () {
    $sanitizer = new ArticleSanitizer();
    expect($sanitizer->sanitize("it\u{2019}s fine"))->toBe("it's fine");
});

it('detects cliches and returns their positions', function () {
    $sanitizer = new ArticleSanitizer();
    $flags = $sanitizer->detectCliches('In dit artikel gaan we duiken in content.');
    expect($flags)->toContain('in dit artikel gaan we');
});

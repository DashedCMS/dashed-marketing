<?php

use Dashed\DashedMarketing\Services\KeywordNormalizer;

it('lowercases and strips diacritics', function () {
    $normalizer = new KeywordNormalizer;
    expect($normalizer->normalize('Waxinelichthöuder', 'nl'))->toBe('waxinelichthouder');
});

it('removes nl stopwords', function () {
    $normalizer = new KeywordNormalizer;
    expect($normalizer->tokens('theelichthouder voor de theepot', 'nl'))
        ->toBe(['theelichthouder', 'theepot']);
});

it('computes jaccard similarity', function () {
    $normalizer = new KeywordNormalizer;
    $a = ['waxinelichthouder', 'design'];
    $b = ['waxinelichthouder', 'modern'];
    expect($normalizer->jaccard($a, $b))->toBeGreaterThan(0.3);
});

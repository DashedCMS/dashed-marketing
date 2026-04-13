<?php

use Dashed\DashedMarketing\Services\ContentMatcher;

it('routes transactional intent to product candidates first', function () {
    $matcher = app(ContentMatcher::class);
    $order = $matcher->candidateContentTypes('transactional');
    expect($order)->toBe(['product', 'category', 'landing_page']);
});

it('routes informational intent to blog first', function () {
    $matcher = app(ContentMatcher::class);
    $order = $matcher->candidateContentTypes('informational');
    expect($order)->toBe(['blog', 'faq', 'landing_page']);
});

<?php

use Dashed\DashedMarketing\Adapters\ManualKeywordDataProvider;

it('returns an empty enrichment map', function () {
    $provider = new ManualKeywordDataProvider();
    $result = $provider->enrich(['waxinelichthouder', 'theepot'], 'nl');

    expect($result)->toBe([]);
});

it('reports zero capabilities', function () {
    $provider = new ManualKeywordDataProvider();

    expect($provider->supports('volume'))->toBeFalse();
    expect($provider->supports('intent'))->toBeFalse();
});

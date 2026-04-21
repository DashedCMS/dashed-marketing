<?php

use Dashed\DashedMarketing\Models\ContentCluster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('casts pending_concepts to an array', function () {
    $cluster = ContentCluster::create([
        'name' => 'Test cluster',
        'locale' => 'nl',
        'theme' => 'SEO',
        'content_type' => 'blog',
        'description' => 'Een testcluster',
        'status' => 'planned',
        'pending_concepts' => [
            [
                'title' => 'A',
                'description' => 'd',
                'suggested_target_type' => 'article',
            ],
        ],
    ]);

    $fresh = $cluster->fresh();

    expect($fresh->pending_concepts)->toBeArray();
    expect($fresh->pending_concepts[0]['title'])->toBe('A');
});

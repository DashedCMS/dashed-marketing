<?php

use Tests\TestCase;
use Dashed\DashedAi\Facades\Ai;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Dashed\DashedMarketing\Models\Keyword;
use Dashed\DashedMarketing\Models\ContentCluster;
use Dashed\DashedMarketing\Jobs\GenerateClusterConceptsJob;

uses(TestCase::class, RefreshDatabase::class);

it('fills pending_concepts on the cluster from AI response', function () {
    $cluster = ContentCluster::create(['name' => 'Cluster', 'locale' => 'nl', 'content_type' => 'blog']);
    $cluster->keywords()->attach(
        Keyword::create(['keyword' => 'fietsen alpen', 'locale' => 'nl', 'status' => 'approved'])->id
    );

    Ai::shouldReceive('json')
        ->once()
        ->andReturn([
            'concepts' => [
                ['title' => 'Top 10 Alpenroutes', 'description' => 'Gids voor beginners', 'suggested_target_type' => 'article'],
                ['title' => 'Materiaal checklist', 'description' => 'Wat heb je nodig', 'suggested_target_type' => 'article'],
            ],
        ]);

    (new GenerateClusterConceptsJob($cluster->id, 2, null))->handle();

    $cluster->refresh();
    expect($cluster->pending_concepts)->toBeArray()
        ->and($cluster->pending_concepts)->toHaveCount(2)
        ->and($cluster->pending_concepts[0]['title'])->toBe('Top 10 Alpenroutes');
});

it('does not set pending_concepts when AI returns empty', function () {
    $cluster = ContentCluster::create(['name' => 'Cluster', 'locale' => 'nl', 'content_type' => 'blog']);

    Ai::shouldReceive('json')->once()->andReturn([]);

    (new GenerateClusterConceptsJob($cluster->id, 3, null))->handle();

    expect($cluster->fresh()->pending_concepts)->toBeNull();
});

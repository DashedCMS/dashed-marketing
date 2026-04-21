<?php

use Tests\TestCase;
use Dashed\DashedAi\Facades\Ai;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Dashed\DashedMarketing\Models\ContentCluster;
use Dashed\DashedMarketing\Models\ContentDraft;
use Dashed\DashedMarketing\Jobs\FillContentDraftJob;

uses(TestCase::class, RefreshDatabase::class);

it('fills h2_sections and sets status ready on success', function () {
    $cluster = ContentCluster::create(['name' => 'C', 'locale' => 'nl', 'content_type' => 'blog']);
    $draft = ContentDraft::create([
        'content_cluster_id' => $cluster->id,
        'name' => 'Mijn titel',
        'slug' => 'mijn-titel',
        'keyword' => 'mijn titel',
        'locale' => 'nl',
        'status' => 'concept',
    ]);

    Ai::shouldReceive('json')->once()->andReturn([
        'h2_sections' => [
            ['id' => 'a', 'heading' => 'H1', 'body' => 'body', 'order' => 0],
        ],
    ]);

    (new FillContentDraftJob($draft->id, null))->handle();

    $draft->refresh();
    expect($draft->status)->toBe('ready')
        ->and($draft->h2_sections)->toHaveCount(1)
        ->and($draft->h2_sections[0]['heading'])->toBe('H1');
});

it('reverts to concept and records error when AI returns empty', function () {
    $cluster = ContentCluster::create(['name' => 'C', 'locale' => 'nl', 'content_type' => 'blog']);
    $draft = ContentDraft::create([
        'content_cluster_id' => $cluster->id,
        'name' => 'Mijn titel',
        'slug' => 'mijn-titel',
        'keyword' => 'mijn titel',
        'locale' => 'nl',
        'status' => 'concept',
    ]);

    Ai::shouldReceive('json')->once()->andReturn([]);

    (new FillContentDraftJob($draft->id, null))->handle();

    $fresh = $draft->fresh();
    expect($fresh->status)->toBe('concept')
        ->and($fresh->error_message)->not->toBeNull();
});

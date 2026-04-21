<?php

use Dashed\DashedAi\Facades\Ai;
use Dashed\DashedMarketing\Jobs\RegenerateSectionHeadingJob;
use Dashed\DashedMarketing\Models\ContentCluster;
use Dashed\DashedMarketing\Models\ContentDraft;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('rewrites heading and intent for the matching section only', function () {
    $cluster = ContentCluster::create(['name' => 'C', 'locale' => 'nl', 'content_type' => 'blog']);
    $draft = ContentDraft::create([
        'content_cluster_id' => $cluster->id,
        'name' => 'Titel',
        'slug' => 'titel',
        'keyword' => 'kw',
        'locale' => 'nl',
        'status' => 'concept',
        'h2_sections' => [
            ['id' => 's1', 'heading' => 'Oud 1', 'intent' => 'oud i1', 'body' => '', 'order' => 0],
            ['id' => 's2', 'heading' => 'Oud 2', 'intent' => 'oud i2', 'body' => 'original body', 'order' => 1],
        ],
    ]);

    Ai::shouldReceive('json')->once()->andReturn([
        'heading' => 'Nieuwe heading',
        'intent' => 'nieuwe intent',
    ]);

    (new RegenerateSectionHeadingJob($draft->id, 's2'))->handle();

    $sections = $draft->fresh()->h2_sections;
    expect($sections[0]['heading'])->toBe('Oud 1')
        ->and($sections[1]['heading'])->toBe('Nieuwe heading')
        ->and($sections[1]['intent'])->toBe('nieuwe intent')
        ->and($sections[1]['body'])->toBe('original body');
});

it('is a no-op when section id is not found', function () {
    $cluster = ContentCluster::create(['name' => 'C', 'locale' => 'nl', 'content_type' => 'blog']);
    $draft = ContentDraft::create([
        'content_cluster_id' => $cluster->id,
        'name' => 'T',
        'slug' => 't',
        'keyword' => 'k',
        'locale' => 'nl',
        'status' => 'concept',
        'h2_sections' => [['id' => 's1', 'heading' => 'H', 'intent' => 'i', 'body' => '', 'order' => 0]],
    ]);

    Ai::shouldReceive('json')->never();

    (new RegenerateSectionHeadingJob($draft->id, 'nonexistent'))->handle();

    expect($draft->fresh()->h2_sections)->toHaveCount(1);
});

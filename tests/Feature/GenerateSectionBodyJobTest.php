<?php

use Dashed\DashedAi\Facades\Ai;
use Dashed\DashedMarketing\Jobs\GenerateSectionBodyJob;
use Dashed\DashedMarketing\Models\ContentCluster;
use Dashed\DashedMarketing\Models\ContentDraft;
use Dashed\DashedMarketing\Services\LinkCandidatesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('fills body of the matching section with HTML', function () {
    $cluster = ContentCluster::create(['name' => 'C', 'locale' => 'nl', 'content_type' => 'blog']);
    $draft = ContentDraft::create([
        'content_cluster_id' => $cluster->id,
        'name' => 'Titel',
        'slug' => 'titel',
        'keyword' => 'kw',
        'locale' => 'nl',
        'status' => 'ready',
        'h2_sections' => [
            ['id' => 's1', 'heading' => 'Intro', 'intent' => 'introduceer', 'body' => '', 'order' => 0],
            ['id' => 's2', 'heading' => 'Details', 'intent' => 'meer diepgang', 'body' => 'BESTAAND', 'order' => 1],
        ],
    ]);

    Ai::shouldReceive('json')->once()->andReturn([
        'body' => '<p>Eerste paragraaf met <a href="/gids-fietsen">gids voor fietsen</a>.</p>',
    ]);

    (new GenerateSectionBodyJob($draft->id, 's1'))->handle(app(LinkCandidatesService::class));

    $sections = $draft->fresh()->h2_sections;
    expect($sections[0]['body'])->toContain('Eerste paragraaf')
        ->and($sections[0]['body'])->toContain('<a href="/gids-fietsen"')
        ->and($sections[1]['body'])->toBe('BESTAAND');
});

it('records section error_message when AI returns empty body', function () {
    $cluster = ContentCluster::create(['name' => 'C', 'locale' => 'nl', 'content_type' => 'blog']);
    $draft = ContentDraft::create([
        'content_cluster_id' => $cluster->id,
        'name' => 'T',
        'slug' => 't',
        'keyword' => 'k',
        'locale' => 'nl',
        'status' => 'ready',
        'h2_sections' => [['id' => 's1', 'heading' => 'H', 'intent' => 'i', 'body' => '', 'order' => 0]],
    ]);

    Ai::shouldReceive('json')->once()->andReturn([]);

    (new GenerateSectionBodyJob($draft->id, 's1'))->handle(app(LinkCandidatesService::class));

    $sections = $draft->fresh()->h2_sections;
    expect($sections[0]['body'])->toBe('');
    expect($sections[0]['error_message'] ?? null)->not->toBeNull();
});

<?php

use Tests\TestCase;
use Livewire\Livewire;
use Illuminate\Support\Facades\Queue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Dashed\DashedMarketing\Models\ContentCluster;
use Dashed\DashedMarketing\Models\ContentDraft;
use Dashed\DashedMarketing\Jobs\GenerateClusterConceptsJob;
use Dashed\DashedMarketing\Filament\Resources\ContentClusterResource\Pages\EditContentCluster;

uses(TestCase::class, RefreshDatabase::class);

it('dispatches GenerateClusterConceptsJob with the chosen count and briefing', function () {
    Queue::fake();

    $cluster = ContentCluster::create(['name' => 'Cluster', 'locale' => 'nl', 'content_type' => 'blog']);

    Livewire::test(EditContentCluster::class, ['record' => $cluster->id])
        ->callAction('generate_concepts', ['count' => 5, 'briefing' => 'focus op beginners']);

    Queue::assertPushed(GenerateClusterConceptsJob::class, function ($job) use ($cluster) {
        return $job->clusterId === $cluster->id
            && $job->count === 5
            && $job->briefing === 'focus op beginners';
    });
});

it('make_drafts creates a ContentDraft per concept and clears pending_concepts', function () {
    $cluster = ContentCluster::create([
        'name' => 'C',
        'locale' => 'nl',
        'content_type' => 'blog',
        'pending_concepts' => [
            ['title' => 'Top 10 routes', 'description' => 'gids', 'suggested_target_type' => 'article'],
            ['title' => 'Materiaal checklist', 'description' => 'wat heb je', 'suggested_target_type' => 'article'],
        ],
    ]);

    Livewire::test(EditContentCluster::class, ['record' => $cluster->id])
        ->callAction('make_drafts');

    $cluster->refresh();
    expect($cluster->pending_concepts)->toBeNull();

    $drafts = ContentDraft::where('content_cluster_id', $cluster->id)->get();
    expect($drafts)->toHaveCount(2)
        ->and($drafts[0]->name)->toBe('Top 10 routes')
        ->and($drafts[0]->slug)->toBe('top-10-routes')
        ->and($drafts[0]->status)->toBe('concept');
});

it('make_drafts is disabled when there are no pending concepts', function () {
    $cluster = ContentCluster::create(['name' => 'C', 'locale' => 'nl', 'content_type' => 'blog']);

    Livewire::test(EditContentCluster::class, ['record' => $cluster->id])
        ->assertActionDisabled('make_drafts');
});

it('repeater shows pending concepts and saves edits', function () {
    $cluster = ContentCluster::create([
        'name' => 'C',
        'locale' => 'nl',
        'content_type' => 'blog',
        'pending_concepts' => [
            ['title' => 'Origineel', 'description' => 'd', 'suggested_target_type' => 'article'],
        ],
    ]);

    $component = Livewire::test(EditContentCluster::class, ['record' => $cluster->id]);

    $state = $component->instance()->form->getState();
    expect(array_values($state['pending_concepts'])[0]['title'])->toBe('Origineel');

    $component->fillForm([
        'pending_concepts' => [
            ['title' => 'Bewerkt', 'description' => 'd', 'suggested_target_type' => 'article'],
        ],
    ])->call('save');

    expect($cluster->fresh()->pending_concepts[0]['title'])->toBe('Bewerkt');
});

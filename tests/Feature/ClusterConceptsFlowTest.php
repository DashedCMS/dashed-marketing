<?php

use Dashed\DashedMarketing\Filament\Resources\ContentClusterResource\Pages\EditContentCluster;
use Dashed\DashedMarketing\Jobs\GenerateClusterConceptsJob;
use Dashed\DashedMarketing\Models\ContentCluster;
use Dashed\DashedMarketing\Models\ContentDraft;
use Dashed\DashedMarketing\Models\Keyword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

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

it('make_drafts creates drafts with keywords, h2 outline and target resolved', function () {
    $cluster = ContentCluster::create(['name' => 'C', 'locale' => 'nl', 'content_type' => 'blog']);
    $kw1 = Keyword::create(['keyword' => 'k1', 'locale' => 'nl', 'status' => 'approved']);
    $kw2 = Keyword::create(['keyword' => 'k2', 'locale' => 'nl', 'status' => 'approved']);
    $cluster->keywords()->attach([$kw1->id, $kw2->id]);

    cms()->builder('routeModels', [
        'fakeTarget' => [
            'name' => 'Fake',
            'pluralName' => 'Fakes',
            'class' => ContentDraft::class,  // any real class works; we only verify the class is copied
            'nameField' => 'name',
        ],
    ]);

    $cluster->update(['pending_concepts' => [
        [
            'title' => 'Top 10 routes',
            'description' => 'gids',
            'suggested_target_type' => 'fakeTarget',
            'target_mode' => 'new',
            'target_id' => null,
            'target_preview_name' => null,
            'keyword_ids' => [$kw1->id, $kw2->id],
            'h2_sections' => [
                ['id' => 'u1', 'heading' => 'Intro', 'intent' => 'leg uit'],
                ['id' => 'u2', 'heading' => 'Details', 'intent' => 'dieper in'],
            ],
        ],
    ]]);

    Livewire::test(EditContentCluster::class, ['record' => $cluster->id])
        ->callAction('make_drafts');

    $cluster->refresh();
    expect($cluster->pending_concepts)->toBeNull();

    $drafts = ContentDraft::where('content_cluster_id', $cluster->id)->get();
    expect($drafts)->toHaveCount(1);
    $draft = $drafts[0];
    expect($draft->name)->toBe('Top 10 routes')
        ->and($draft->slug)->toBe('top-10-routes')
        ->and($draft->status)->toBe('concept')
        ->and($draft->h2_sections)->toHaveCount(2)
        ->and($draft->h2_sections[0]['heading'])->toBe('Intro')
        ->and($draft->h2_sections[0]['intent'])->toBe('leg uit')
        ->and($draft->h2_sections[0]['body'])->toBe('')
        ->and($draft->keywords()->count())->toBe(2);
});

it('make_drafts copies target_id into subject_id for overwrite mode', function () {
    $cluster = ContentCluster::create(['name' => 'C', 'locale' => 'nl', 'content_type' => 'blog']);

    cms()->builder('routeModels', [
        'fakeTarget' => [
            'name' => 'Fake',
            'pluralName' => 'Fakes',
            'class' => 'App\\Models\\NonExistentButRegistered',
            'nameField' => 'name',
        ],
    ]);

    $cluster->update(['pending_concepts' => [
        [
            'title' => 'Overschrijf',
            'description' => '',
            'suggested_target_type' => 'fakeTarget',
            'target_mode' => 'overwrite',
            'target_id' => 42,
            'target_preview_name' => 'Oud',
            'keyword_ids' => [],
            'h2_sections' => [['id' => 'u1', 'heading' => 'Enig', 'intent' => 'solo']],
        ],
    ]]);

    Livewire::test(EditContentCluster::class, ['record' => $cluster->id])
        ->callAction('make_drafts');

    $draft = ContentDraft::where('content_cluster_id', $cluster->id)->first();
    expect($draft->subject_type)->toBe('App\\Models\\NonExistentButRegistered')
        ->and($draft->subject_id)->toBe(42);
});

it('make_drafts is disabled when there are no pending concepts', function () {
    $cluster = ContentCluster::create(['name' => 'C', 'locale' => 'nl', 'content_type' => 'blog']);

    Livewire::test(EditContentCluster::class, ['record' => $cluster->id])
        ->assertActionDisabled('make_drafts');
});

it('repeater shows pending concepts and saves edits', function () {
    cms()->builder('routeModels', []);  // overwrite any leaked state
    $cluster = ContentCluster::create([
        'name' => 'C',
        'locale' => 'nl',
        'content_type' => 'blog',
        'pending_concepts' => [
            [
                'title' => 'Origineel',
                'description' => 'd',
                'suggested_target_type' => 'article',
                'target_mode' => 'new',
                'target_id' => null,
                'target_preview_name' => null,
                'keyword_ids' => [],
                'h2_sections' => [['id' => 'u1', 'heading' => 'h', 'intent' => 'i']],
            ],
        ],
    ]);

    $component = Livewire::test(EditContentCluster::class, ['record' => $cluster->id]);

    $state = $component->instance()->form->getState();
    expect(array_values($state['pending_concepts'])[0]['title'])->toBe('Origineel');

    $component->fillForm([
        'pending_concepts' => [
            [
                'title' => 'Bewerkt',
                'description' => 'd',
                'suggested_target_type' => 'article',
                'target_mode' => 'new',
                'target_id' => null,
                'target_preview_name' => null,
                'keyword_ids' => [],
                'h2_sections' => [['id' => 'u1', 'heading' => 'h', 'intent' => 'i']],
            ],
        ],
    ])->call('save');

    expect($cluster->fresh()->pending_concepts[0]['title'])->toBe('Bewerkt');
});

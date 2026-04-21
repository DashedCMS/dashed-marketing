<?php

use Dashed\DashedMarketing\Filament\Resources\ContentDraftResource\Pages\EditContentDraft;
use Dashed\DashedMarketing\Models\ContentCluster;
use Dashed\DashedMarketing\Models\ContentDraft;
use Dashed\DashedMarketing\Models\Keyword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('renders name, slug on the edit form', function () {
    $cluster = ContentCluster::create(['name' => 'C', 'locale' => 'nl', 'content_type' => 'blog']);
    $draft = ContentDraft::create([
        'content_cluster_id' => $cluster->id,
        'name' => 'Titel',
        'slug' => 'titel',
        'keyword' => 'kw',
        'locale' => 'nl',
        'status' => 'concept',
        'h2_sections' => [
            ['id' => 's1', 'heading' => 'H1', 'intent' => 'i1', 'body' => 'b1', 'order' => 0],
        ],
    ]);

    Livewire::test(EditContentDraft::class, ['record' => $draft->id])
        ->assertFormSet(['name' => 'Titel', 'slug' => 'titel']);
});

it('saves edited h2_sections with intent and body updates', function () {
    $cluster = ContentCluster::create(['name' => 'C', 'locale' => 'nl', 'content_type' => 'blog']);
    $draft = ContentDraft::create([
        'content_cluster_id' => $cluster->id,
        'name' => 'Titel',
        'slug' => 'titel',
        'keyword' => 'kw',
        'locale' => 'nl',
        'status' => 'concept',
        'h2_sections' => [
            ['id' => 's1', 'heading' => 'Oud', 'intent' => 'oud i', 'body' => '', 'order' => 0],
        ],
    ]);

    Livewire::test(EditContentDraft::class, ['record' => $draft->id])
        ->fillForm([
            'h2_sections' => [
                ['id' => 's1', 'heading' => 'Nieuw', 'intent' => 'nieuw i', 'body' => '<p>Body</p>', 'order' => 0],
            ],
        ])
        ->call('save');

    $sections = $draft->fresh()->h2_sections;
    expect($sections[0]['heading'])->toBe('Nieuw')
        ->and($sections[0]['intent'])->toBe('nieuw i')
        ->and($sections[0]['body'])->toBe('<p>Body</p>');
});

it('attaches keywords via multiselect on save', function () {
    $cluster = ContentCluster::create(['name' => 'C', 'locale' => 'nl', 'content_type' => 'blog']);
    $kw = Keyword::create(['keyword' => 'k', 'locale' => 'nl', 'status' => 'approved']);
    $cluster->keywords()->attach($kw->id);
    $draft = ContentDraft::create([
        'content_cluster_id' => $cluster->id,
        'name' => 'T',
        'slug' => 't',
        'keyword' => 'k',
        'locale' => 'nl',
        'status' => 'concept',
    ]);

    Livewire::test(EditContentDraft::class, ['record' => $draft->id])
        ->fillForm(['keywords' => [$kw->id]])
        ->call('save');

    expect($draft->fresh()->keywords()->count())->toBe(1);
});

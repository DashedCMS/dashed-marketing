<?php

use Dashed\DashedMarketing\Filament\Resources\ContentClusterResource\Pages\EditContentCluster;
use Dashed\DashedMarketing\Models\ContentCluster;
use Dashed\DashedMarketing\Models\Keyword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('saves target_id, keyword_ids and nested h2_sections edits', function () {
    cms()->builder('routeModels', []);  // overwrite any leaked state
    $kw = Keyword::create(['keyword' => 'a', 'locale' => 'nl', 'status' => 'approved']);
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
    $cluster->keywords()->attach($kw->id);

    Livewire::test(EditContentCluster::class, ['record' => $cluster->id])
        ->fillForm([
            'pending_concepts' => [
                [
                    'title' => 'Bewerkt',
                    'description' => 'd',
                    'suggested_target_type' => 'article',
                    'target_mode' => 'new',
                    'target_id' => null,
                    'target_preview_name' => null,
                    'keyword_ids' => [$kw->id],
                    'h2_sections' => [
                        ['id' => 'u1', 'heading' => 'nieuw', 'intent' => 'nieuwi'],
                        ['id' => 'u2', 'heading' => 'extra', 'intent' => 'extra i'],
                    ],
                ],
            ],
        ])
        ->call('save');

    $cluster->refresh();
    $c = $cluster->pending_concepts[0];
    expect($c['title'])->toBe('Bewerkt')
        ->and($c['keyword_ids'])->toBe([$kw->id])
        ->and($c['h2_sections'])->toHaveCount(2)
        ->and($c['h2_sections'][1]['heading'])->toBe('extra');
});

<?php

use Tests\TestCase;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Dashed\DashedMarketing\Models\Keyword;
use Dashed\DashedMarketing\Models\ContentCluster;
use Dashed\DashedMarketing\Filament\Resources\KeywordResource\Pages\ListKeywords;

uses(TestCase::class, RefreshDatabase::class);

it('creates new cluster and attaches keywords', function () {
    $a = Keyword::create(['keyword' => 'kw a', 'locale' => 'nl', 'status' => 'approved']);
    $b = Keyword::create(['keyword' => 'kw b', 'locale' => 'nl', 'status' => 'approved']);

    Livewire::test(ListKeywords::class)
        ->callTableBulkAction('attach_cluster', [$a->id, $b->id], [
            'koppel_modus' => 'new',
            'name' => 'Mijn cluster',
            'content_type' => 'blog',
            'locale' => 'nl',
            'keywords' => [$a->id, $b->id],
        ]);

    $cluster = ContentCluster::where('name', 'Mijn cluster')->first();
    expect($cluster)->not->toBeNull();
    expect($cluster->locale)->toBe('nl');
    expect($cluster->keywords()->count())->toBe(2);
});

it('attaches keywords to an existing cluster', function () {
    $existing = Keyword::create(['keyword' => 'existing kw', 'locale' => 'nl', 'status' => 'approved']);
    $new = Keyword::create(['keyword' => 'new kw', 'locale' => 'nl', 'status' => 'approved']);

    $cluster = ContentCluster::create([
        'name' => 'Bestaande cluster',
        'content_type' => 'blog',
        'locale' => 'nl',
        'status' => 'planned',
    ]);
    $cluster->keywords()->attach($existing->id);

    Livewire::test(ListKeywords::class)
        ->callTableBulkAction('attach_cluster', [$new->id], [
            'koppel_modus' => 'existing',
            'cluster_id' => $cluster->id,
            'keywords_to_add' => [$new->id],
        ]);

    expect($cluster->fresh()->keywords()->count())->toBe(2);
});

it('does not detach current keywords when adding to existing cluster', function () {
    $existing = Keyword::create(['keyword' => 'keep me', 'locale' => 'nl', 'status' => 'approved']);
    $new = Keyword::create(['keyword' => 'add me', 'locale' => 'nl', 'status' => 'approved']);

    $cluster = ContentCluster::create([
        'name' => 'Cluster behoud',
        'content_type' => 'blog',
        'locale' => 'nl',
        'status' => 'planned',
    ]);
    $cluster->keywords()->attach($existing->id);

    Livewire::test(ListKeywords::class)
        ->callTableBulkAction('attach_cluster', [$new->id], [
            'koppel_modus' => 'existing',
            'cluster_id' => $cluster->id,
            'keywords_to_add' => [$new->id],
        ]);

    expect(
        \Illuminate\Support\Facades\DB::table('dashed__content_cluster_keyword')
            ->where('content_cluster_id', $cluster->id)
            ->where('keyword_id', $existing->id)
            ->exists()
    )->toBeTrue();
});

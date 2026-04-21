<?php

use Tests\TestCase;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Dashed\DashedMarketing\Models\Keyword;
use Dashed\DashedMarketing\Models\ContentCluster;
use Dashed\DashedMarketing\Filament\Resources\ContentClusterResource\Pages\EditContentCluster;

uses(TestCase::class, RefreshDatabase::class);

it('allows attaching keywords via edit form', function () {
    $cluster = ContentCluster::create(['name' => 'Cluster', 'locale' => 'nl', 'content_type' => 'blog']);
    $a = Keyword::create(['keyword' => 'kw a', 'locale' => 'nl', 'status' => 'approved']);
    $b = Keyword::create(['keyword' => 'kw b', 'locale' => 'nl', 'status' => 'approved']);

    Livewire::test(EditContentCluster::class, ['record' => $cluster->id])
        ->fillForm(['keywords' => [$a->id, $b->id]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($cluster->fresh()->keywords)->toHaveCount(2);
});

it('allows detaching keywords via edit form', function () {
    $cluster = ContentCluster::create(['name' => 'Cluster', 'locale' => 'nl', 'content_type' => 'blog']);
    $a = Keyword::create(['keyword' => 'kw a', 'locale' => 'nl', 'status' => 'approved']);
    $b = Keyword::create(['keyword' => 'kw b', 'locale' => 'nl', 'status' => 'approved']);
    $cluster->keywords()->attach([$a->id, $b->id]);

    Livewire::test(EditContentCluster::class, ['record' => $cluster->id])
        ->fillForm(['keywords' => [$a->id]])
        ->call('save');

    expect($cluster->fresh()->keywords)->toHaveCount(1);
});

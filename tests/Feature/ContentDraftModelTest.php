<?php

use Dashed\DashedMarketing\Models\ContentCluster;
use Dashed\DashedMarketing\Models\ContentDraft;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('allows name and slug as fillable', function () {
    $cluster = ContentCluster::create([
        'name' => 'Cluster voor draft',
        'locale' => 'nl',
        'theme' => 'SEO',
        'content_type' => 'blog',
        'description' => 'Een testcluster',
        'status' => 'planned',
    ]);

    $draft = ContentDraft::create([
        'content_cluster_id' => $cluster->id,
        'name' => 'Mijn pagina titel',
        'slug' => 'mijn-pagina-titel',
        'keyword' => 'mijn pagina',
        'locale' => 'nl',
        'status' => 'concept',
    ]);

    $fresh = $draft->fresh();

    expect($fresh->name)->toBe('Mijn pagina titel');
    expect($fresh->slug)->toBe('mijn-pagina-titel');
});

it('labels concept status correctly', function () {
    $draft = new ContentDraft(['status' => 'concept']);

    expect($draft->status_label)->toBe('Concept');
    expect($draft->status_color)->toBe('gray');
});
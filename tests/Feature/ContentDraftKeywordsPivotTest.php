<?php

use Dashed\DashedMarketing\Models\ContentCluster;
use Dashed\DashedMarketing\Models\ContentDraft;
use Dashed\DashedMarketing\Models\Keyword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('creates the content_draft_keyword pivot table', function () {
    expect(Schema::hasTable('dashed__content_draft_keyword'))->toBeTrue();
    expect(Schema::hasColumn('dashed__content_draft_keyword', 'content_draft_id'))->toBeTrue();
    expect(Schema::hasColumn('dashed__content_draft_keyword', 'keyword_id'))->toBeTrue();
});

it('attaches keywords via the pivot relation', function () {
    $cluster = ContentCluster::create(['name' => 'C', 'locale' => 'nl', 'content_type' => 'blog']);
    $draft = ContentDraft::create([
        'content_cluster_id' => $cluster->id,
        'name' => 'Titel',
        'slug' => 'titel',
        'keyword' => 'kw',
        'locale' => 'nl',
        'status' => 'concept',
    ]);
    $a = Keyword::create(['keyword' => 'a', 'locale' => 'nl', 'status' => 'approved']);
    $b = Keyword::create(['keyword' => 'b', 'locale' => 'nl', 'status' => 'approved']);

    $draft->keywords()->attach([$a->id, $b->id]);

    expect($draft->keywords()->count())->toBe(2);
    expect($draft->keywords->pluck('keyword')->all())->toEqualCanonicalizing(['a', 'b']);
});

it('cascade deletes pivot rows when draft is deleted', function () {
    $cluster = ContentCluster::create(['name' => 'C', 'locale' => 'nl', 'content_type' => 'blog']);
    $draft = ContentDraft::create([
        'content_cluster_id' => $cluster->id,
        'name' => 'Titel',
        'slug' => 'titel',
        'keyword' => 'kw',
        'locale' => 'nl',
        'status' => 'concept',
    ]);
    $kw = Keyword::create(['keyword' => 'a', 'locale' => 'nl', 'status' => 'approved']);
    $draft->keywords()->attach($kw->id);

    $draft->delete();

    expect(DB::table('dashed__content_draft_keyword')->count())->toBe(0);
});

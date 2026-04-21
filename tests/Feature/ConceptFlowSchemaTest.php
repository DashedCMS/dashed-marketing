<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('has pending_concepts column on content_clusters', function () {
    expect(Schema::hasColumn('dashed__content_clusters', 'pending_concepts'))->toBeTrue();
});

it('has name and slug columns on content_drafts', function () {
    expect(Schema::hasColumn('dashed__content_drafts', 'name'))->toBeTrue();
    expect(Schema::hasColumn('dashed__content_drafts', 'slug'))->toBeTrue();
});

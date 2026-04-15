<?php

// Task 1 (Phase 1 Omnisocials plan): schema assertions only.
// Task 3 and Task 4 will add seeder idempotency and field-value assertions to this file.

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Schema::dropIfExists('dashed__social_channels');

    $migration = require __DIR__ . '/../../database/migrations/2026_04_15_000002_create_social_channels_table.php';
    $migration->up();
});

afterEach(function () {
    Schema::dropIfExists('dashed__social_channels');
});

it('creates the dashed__social_channels table with the expected columns', function () {
    expect(Schema::hasTable('dashed__social_channels'))->toBeTrue();

    expect(Schema::hasColumns('dashed__social_channels', [
        'id',
        'site_id',
        'name',
        'slug',
        'accepted_types',
        'meta',
        'order',
        'is_active',
        'created_at',
        'updated_at',
    ]))->toBeTrue();
});

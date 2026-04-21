<?php

// Task 1 (Phase 1 Omnisocials plan): schema assertions only.
// Task 3 and Task 4 will add seeder idempotency and field-value assertions to this file.

use Dashed\DashedMarketing\Database\Seeders\SocialChannelSeeder;
use Dashed\DashedMarketing\Models\SocialChannel;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Schema::dropIfExists('dashed__social_channels');

    $migration = require __DIR__.'/../../database/migrations/2026_04_15_000002_create_social_channels_table.php';
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

it('seeds every channel from DEFAULTS for a given site id', function () {
    SocialChannel::query()->withoutGlobalScopes()->delete();

    (new SocialChannelSeeder)->seedSite('default');

    $channels = SocialChannel::query()->withoutGlobalScopes()->where('site_id', 'default')->get();

    expect($channels->count())->toBe(count(SocialChannelSeeder::DEFAULTS));

    $instagram = $channels->firstWhere('slug', 'instagram_feed');
    expect($instagram)->not->toBeNull();
    expect($instagram->name)->toBe('Instagram Feed');
    expect($instagram->accepted_types)->toBe(['post']);
    expect($instagram->meta['caption_min'])->toBe(125);
    expect($instagram->meta['caption_max'])->toBe(300);
    expect($instagram->meta['hashtags_min'])->toBe(10);
    expect($instagram->meta['hashtags_max'])->toBe(15);
    expect($instagram->meta['tips'])->toContain('Hook in eerste zin');
    expect($instagram->is_active)->toBeTrue();
});

it('is idempotent: running the seeder twice does not duplicate rows', function () {
    SocialChannel::query()->withoutGlobalScopes()->delete();

    (new SocialChannelSeeder)->seedSite('default');
    (new SocialChannelSeeder)->seedSite('default');

    $count = SocialChannel::query()->withoutGlobalScopes()->where('site_id', 'default')->count();
    expect($count)->toBe(count(SocialChannelSeeder::DEFAULTS));
});

it('seeds all registered sites when the migration runs', function () {
    cms()->builder('sites', [
        ['id' => 'site_a', 'name' => 'Site A', 'locales' => []],
        ['id' => 'site_b', 'name' => 'Site B', 'locales' => []],
    ]);

    SocialChannel::query()->withoutGlobalScopes()->delete();

    $migration = require __DIR__.'/../../database/migrations/2026_04_15_000002_create_social_channels_table.php';
    $migration->seedExistingSites();

    $siteA = SocialChannel::query()->withoutGlobalScopes()->where('site_id', 'site_a')->get();
    $siteB = SocialChannel::query()->withoutGlobalScopes()->where('site_id', 'site_b')->get();

    expect($siteA)->toHaveCount(count(SocialChannelSeeder::DEFAULTS));
    expect($siteB)->toHaveCount(count(SocialChannelSeeder::DEFAULTS));
});

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

it('seeds every channel from config for a given site id', function () {
    config()->set('dashed-marketing.channels', [
        'instagram_feed' => [
            'label' => 'Instagram Feed',
            'accepted_types' => ['post'],
            'caption_min' => 125,
            'caption_max' => 300,
            'hashtags_min' => 10,
            'hashtags_max' => 15,
            'tips' => 'Hook in eerste zin',
        ],
        'tiktok' => [
            'label' => 'TikTok',
            'accepted_types' => ['reel'],
            'caption_min' => 10,
            'caption_max' => 80,
            'hashtags_min' => 3,
            'hashtags_max' => 5,
            'tips' => 'Hook + trend',
        ],
    ]);

    SocialChannel::query()->withoutGlobalScopes()->delete();

    (new SocialChannelSeeder)->seedSite('default');

    $channels = SocialChannel::query()->withoutGlobalScopes()->where('site_id', 'default')->get();

    expect($channels)->toHaveCount(2);

    $instagram = $channels->firstWhere('slug', 'instagram_feed');
    expect($instagram->name)->toBe('Instagram Feed');
    expect($instagram->accepted_types)->toBe(['post']);
    expect($instagram->meta)->toBe([
        'caption_min' => 125,
        'caption_max' => 300,
        'hashtags_min' => 10,
        'hashtags_max' => 15,
        'tips' => 'Hook in eerste zin',
    ]);
    expect($instagram->is_active)->toBeTrue();
});

it('is idempotent: running the seeder twice does not duplicate rows', function () {
    config()->set('dashed-marketing.channels', [
        'x' => [
            'label' => 'X (Twitter)',
            'accepted_types' => ['post'],
            'caption_min' => 0,
            'caption_max' => 280,
            'hashtags_min' => 0,
            'hashtags_max' => 2,
            'tips' => 'Scherp en kort',
        ],
    ]);

    SocialChannel::query()->withoutGlobalScopes()->delete();

    (new SocialChannelSeeder)->seedSite('default');
    (new SocialChannelSeeder)->seedSite('default');

    $count = SocialChannel::query()->withoutGlobalScopes()->where('site_id', 'default')->count();
    expect($count)->toBe(1);
});

it('seeds all registered sites when the migration runs', function () {
    config()->set('dashed-marketing.channels', [
        'instagram_feed' => [
            'label' => 'Instagram Feed',
            'accepted_types' => ['post'],
            'caption_min' => 125,
            'caption_max' => 300,
            'hashtags_min' => 10,
            'hashtags_max' => 15,
            'tips' => 'test',
        ],
    ]);

    cms()->builder('sites', [
        ['id' => 'site_a', 'name' => 'Site A', 'locales' => []],
        ['id' => 'site_b', 'name' => 'Site B', 'locales' => []],
    ]);

    SocialChannel::query()->withoutGlobalScopes()->delete();

    $migration = require __DIR__ . '/../../database/migrations/2026_04_15_000002_create_social_channels_table.php';
    $migration->seedExistingSites();

    $siteA = SocialChannel::query()->withoutGlobalScopes()->where('site_id', 'site_a')->get();
    $siteB = SocialChannel::query()->withoutGlobalScopes()->where('site_id', 'site_b')->get();

    expect($siteA)->toHaveCount(1);
    expect($siteB)->toHaveCount(1);
});

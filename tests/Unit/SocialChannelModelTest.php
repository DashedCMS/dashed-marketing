<?php

use Dashed\DashedMarketing\Models\SocialChannel;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Schema::dropIfExists('dashed__social_channels');

    $migration = require __DIR__ . '/../../database/migrations/2026_04_15_000002_create_social_channels_table.php';
    $migration->up();

    SocialChannel::query()->withoutGlobalScopes()->delete();
});

afterEach(function () {
    Schema::dropIfExists('dashed__social_channels');
});

it('casts accepted_types and meta to arrays', function () {
    config()->set('dashed-core.dashed_site_id', 'default');
    cms()->builder('sites', [['id' => 'default', 'name' => 'Default', 'locales' => []]]);

    $channel = SocialChannel::create([
        'site_id' => 'default',
        'name' => 'Instagram Feed',
        'slug' => 'instagram_feed',
        'accepted_types' => ['post'],
        'meta' => ['caption_min' => 125, 'caption_max' => 300],
        'order' => 1,
        'is_active' => true,
    ]);

    $fresh = SocialChannel::find($channel->id);

    expect($fresh->accepted_types)->toBe(['post']);
    expect($fresh->meta)->toBe(['caption_min' => 125, 'caption_max' => 300]);
    expect($fresh->is_active)->toBeTrue();
});

it('automatically sets site_id on create when not provided', function () {
    config()->set('dashed-core.dashed_site_id', 'default');
    cms()->builder('sites', [['id' => 'default', 'name' => 'Default', 'locales' => []]]);

    $channel = SocialChannel::create([
        'name' => 'Test Channel',
        'slug' => 'test_channel',
        'accepted_types' => ['post'],
    ]);

    expect($channel->site_id)->toBe('default');
});

it('scopes queries to the active site by default', function () {
    SocialChannel::withoutGlobalScopes()->create([
        'site_id' => 'other',
        'name' => 'Other Site Channel',
        'slug' => 'other_channel',
        'accepted_types' => ['post'],
    ]);

    SocialChannel::withoutGlobalScopes()->create([
        'site_id' => 'default',
        'name' => 'Default Site Channel',
        'slug' => 'default_channel',
        'accepted_types' => ['post'],
    ]);

    config()->set('dashed-core.dashed_site_id', 'default');
    cms()->builder('sites', [['id' => 'default', 'name' => 'Default', 'locales' => []]]);

    $channels = SocialChannel::all();

    expect($channels)->toHaveCount(1);
    expect($channels->first()->slug)->toBe('default_channel');
});

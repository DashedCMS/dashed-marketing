<?php

use Dashed\DashedMarketing\Filament\Resources\SocialPostResource;
use Dashed\DashedMarketing\Models\SocialChannel;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Schema::dropIfExists('dashed__social_channels');
    $migration = require __DIR__ . '/../../database/migrations/2026_04_15_000002_create_social_channels_table.php';
    $migration->up();

    SocialChannel::query()->withoutGlobalScopes()->delete();

    cms()->builder('sites', [['id' => 'default', 'name' => 'Default', 'locales' => []]]);
    config()->set('dashed-core.dashed_site_id', 'default');

    config()->set('dashed-marketing.channels', []);
});

afterEach(function () {
    Schema::dropIfExists('dashed__social_channels');
});

it('reads channel options from the social_channels table', function () {
    SocialChannel::create([
        'site_id' => 'default',
        'name' => 'Instagram Feed (DB)',
        'slug' => 'instagram_feed',
        'accepted_types' => ['post'],
        'meta' => ['caption_max' => 300],
        'order' => 1,
        'is_active' => true,
    ]);

    SocialChannel::create([
        'site_id' => 'default',
        'name' => 'Inactive Channel',
        'slug' => 'inactive_channel',
        'accepted_types' => ['post'],
        'order' => 2,
        'is_active' => false,
    ]);

    $options = SocialPostResource::getChannelOptions('post');

    expect($options)->toHaveKey('instagram_feed');
    expect($options['instagram_feed'])->toBe('Instagram Feed (DB)');
    expect($options)->not->toHaveKey('inactive_channel');
});

it('resolves channel labels from the database', function () {
    SocialChannel::create([
        'site_id' => 'default',
        'name' => 'Custom LinkedIn Label',
        'slug' => 'linkedin_company',
        'accepted_types' => ['post'],
        'order' => 1,
        'is_active' => true,
    ]);

    $label = SocialPostResource::resolveChannelLabel('linkedin_company');

    expect($label)->toBe('Custom LinkedIn Label');
});

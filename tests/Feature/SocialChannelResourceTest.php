<?php

use Dashed\DashedMarketing\Filament\Resources\SocialChannelResource;
use Dashed\DashedMarketing\Models\SocialChannel;
use Filament\Facades\Filament;
use Tests\TestCase;

uses(TestCase::class);

it('has the expected Filament resource metadata', function () {
    expect(SocialChannelResource::getModel())->toBe(SocialChannel::class);
    expect(SocialChannelResource::getNavigationLabel())->toBe('Kanalen');
});

it('exposes index, create and edit pages', function () {
    $pages = SocialChannelResource::getPages();

    expect($pages)->toHaveKey('index');
    expect($pages)->toHaveKey('create');
    expect($pages)->toHaveKey('edit');
});

it('registers SocialChannelResource in the admin panel', function () {
    $panel = Filament::getPanel('dashed');
    $resources = $panel->getResources();

    expect(in_array(SocialChannelResource::class, $resources, true))->toBeTrue();
});

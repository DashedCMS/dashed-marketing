<?php

use Dashed\DashedMarketing\Adapters\ManualPublishAdapter;
use Dashed\DashedMarketing\Managers\PublishingAdapterRegistry;

beforeEach(function () {
    PublishingAdapterRegistry::clear();
});

it('registers an adapter and retrieves it by slug', function () {
    PublishingAdapterRegistry::register('manual', ManualPublishAdapter::class, 'Handmatig');

    expect(PublishingAdapterRegistry::exists('manual'))->toBeTrue();
    expect(PublishingAdapterRegistry::get('manual'))->toBe([
        'label' => 'Handmatig',
        'class' => ManualPublishAdapter::class,
    ]);
});

it('returns null when an adapter slug is not registered', function () {
    expect(PublishingAdapterRegistry::exists('nope'))->toBeFalse();
    expect(PublishingAdapterRegistry::get('nope'))->toBeNull();
});

it('returns a slug to label map via all()', function () {
    PublishingAdapterRegistry::register('manual', ManualPublishAdapter::class, 'Handmatig');
    PublishingAdapterRegistry::register('fake', ManualPublishAdapter::class, 'Fake adapter');

    expect(PublishingAdapterRegistry::all())->toBe([
        'manual' => 'Handmatig',
        'fake' => 'Fake adapter',
    ]);
});

it('overwrites an existing registration when the same slug is registered twice', function () {
    PublishingAdapterRegistry::register('manual', ManualPublishAdapter::class, 'Handmatig');
    PublishingAdapterRegistry::register('manual', ManualPublishAdapter::class, 'Manual mode');

    expect(PublishingAdapterRegistry::get('manual'))->toBe([
        'label' => 'Manual mode',
        'class' => ManualPublishAdapter::class,
    ]);
});

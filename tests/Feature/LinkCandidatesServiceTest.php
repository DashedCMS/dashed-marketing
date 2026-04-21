<?php

use Dashed\DashedMarketing\Services\LinkCandidatesService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

class FakeRouteModelForLinks extends Model
{
    protected $table = 'fake_link_models';

    protected $guarded = [];

    protected $casts = ['name' => 'array', 'slug' => 'array'];

    public $timestamps = true;
}

beforeEach(function () {
    Schema::dropIfExists('fake_link_models');
    Schema::create('fake_link_models', function (Blueprint $table) {
        $table->id();
        $table->json('name')->nullable();
        $table->json('slug')->nullable();
        $table->timestamps();
    });

    cms()->builder('routeModels', [
        'fakeRouteModelForLinks' => [
            'name' => 'Fake',
            'pluralName' => 'Fakes',
            'class' => FakeRouteModelForLinks::class,
            'nameField' => 'name',
        ],
    ]);
});

it('resolves translatable name for the given locale', function () {
    FakeRouteModelForLinks::create([
        'name' => ['nl' => 'Gids voor fietsen', 'en' => 'Cycling guide'],
        'slug' => ['nl' => 'gids-fietsen', 'en' => 'cycling-guide'],
    ]);

    $candidates = app(LinkCandidatesService::class)->forLocale('nl', 20);

    expect($candidates)->toHaveCount(1)
        ->and($candidates[0]['title'])->toBe('Gids voor fietsen')
        ->and($candidates[0]['url'])->toContain('gids-fietsen')
        ->and($candidates[0]['type'])->toBe('FakeRouteModelForLinks');
});

it('returns empty array when no route models are registered', function () {
    cms()->builder('routeModels', []);
    $candidates = app(LinkCandidatesService::class)->forLocale('nl');
    expect($candidates)->toBe([]);
});

it('respects the limit argument', function () {
    for ($i = 0; $i < 5; $i++) {
        FakeRouteModelForLinks::create([
            'name' => ['nl' => "Gids {$i}"],
            'slug' => ['nl' => "gids-{$i}"],
        ]);
    }

    $candidates = app(LinkCandidatesService::class)->forLocale('nl', 3);
    expect($candidates)->toHaveCount(3);
});

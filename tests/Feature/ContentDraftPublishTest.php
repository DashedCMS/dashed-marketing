<?php

use Dashed\DashedMarketing\Filament\Resources\ContentDraftResource\Pages\EditContentDraft;
use Dashed\DashedMarketing\Models\ContentCluster;
use Dashed\DashedMarketing\Models\ContentDraft;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

class FakeVisitableModel extends Model
{
    protected $table = 'fake_visitable_models';

    protected $guarded = [];

    protected $casts = ['name' => 'array', 'slug' => 'array', 'content' => 'array'];

    public $timestamps = true;
}

beforeEach(function () {
    Schema::dropIfExists('fake_visitable_models');
    Schema::create('fake_visitable_models', function (Blueprint $table) {
        $table->id();
        $table->json('name')->nullable();
        $table->json('slug')->nullable();
        $table->json('content')->nullable();
        $table->timestamps();
    });

    cms()->builder('routeModels', [
        'fakeVisitableModel' => [
            'name' => 'Fake',
            'pluralName' => 'Fakes',
            'class' => FakeVisitableModel::class,
            'nameField' => 'name',
        ],
    ]);
});

it('publish action creates a new target record', function () {
    $cluster = ContentCluster::create(['name' => 'C', 'locale' => 'nl', 'content_type' => 'blog']);
    $draft = ContentDraft::create([
        'content_cluster_id' => $cluster->id,
        'name' => 'Nieuwe pagina',
        'slug' => 'nieuwe-pagina',
        'keyword' => 'kw',
        'locale' => 'nl',
        'status' => 'ready',
        'h2_sections' => [
            ['id' => 'a', 'heading' => 'H1', 'body' => 'body', 'order' => 0],
        ],
    ]);

    Livewire::test(EditContentDraft::class, ['record' => $draft->id])
        ->callAction('publish', [
            'target_type' => 'fakeVisitableModel',
            'target_id' => null,
        ]);

    $draft->refresh();
    expect($draft->status)->toBe('applied')
        ->and($draft->subject_type)->toBe(FakeVisitableModel::class)
        ->and($draft->subject_id)->not->toBeNull();

    $entity = FakeVisitableModel::find($draft->subject_id);
    expect($entity->name)->toBe(['nl' => 'Nieuwe pagina'])
        ->and($entity->slug)->toBe(['nl' => 'nieuwe-pagina']);
});

it('publish action updates an existing target record', function () {
    $existing = FakeVisitableModel::create([
        'name' => ['nl' => 'Oud', 'en' => 'Old'],
        'slug' => ['nl' => 'oud', 'en' => 'old'],
        'content' => ['nl' => []],
    ]);

    $cluster = ContentCluster::create(['name' => 'C', 'locale' => 'nl', 'content_type' => 'blog']);
    $draft = ContentDraft::create([
        'content_cluster_id' => $cluster->id,
        'name' => 'Nieuwe titel',
        'slug' => 'nieuwe-titel',
        'keyword' => 'kw',
        'locale' => 'nl',
        'status' => 'ready',
        'h2_sections' => [['id' => 'a', 'heading' => 'H1', 'body' => 'body', 'order' => 0]],
    ]);

    Livewire::test(EditContentDraft::class, ['record' => $draft->id])
        ->callAction('publish', [
            'target_type' => 'fakeVisitableModel',
            'target_id' => $existing->id,
        ]);

    $existing->refresh();
    expect($existing->name['nl'])->toBe('Nieuwe titel')
        ->and($existing->name['en'])->toBe('Old')  // other locale preserved
        ->and($existing->slug['nl'])->toBe('nieuwe-titel');

    $draft->refresh();
    expect($draft->status)->toBe('applied')
        ->and($draft->subject_id)->toBe($existing->id);
});

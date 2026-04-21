<?php

use Dashed\DashedAi\Facades\Ai;
use Dashed\DashedMarketing\Jobs\GenerateClusterConceptsJob;
use Dashed\DashedMarketing\Models\ContentCluster;
use Dashed\DashedMarketing\Models\Keyword;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

class FakeRouteModelForConcepts extends Model
{
    protected $table = 'fake_concept_models';

    protected $guarded = [];

    protected $casts = ['name' => 'array', 'slug' => 'array'];

    public $timestamps = true;
}

beforeEach(function () {
    Schema::dropIfExists('fake_concept_models');
    Schema::create('fake_concept_models', function (Blueprint $table) {
        $table->id();
        $table->json('name')->nullable();
        $table->json('slug')->nullable();
        $table->timestamps();
    });

    // Explicitly set to overwrite any leaked state from previous tests.
    cms()->builder('routeModels', [
        'fakeRouteModelForConcepts' => [
            'name' => 'FakeConcept',
            'pluralName' => 'FakeConcepts',
            'class' => FakeRouteModelForConcepts::class,
            'nameField' => 'name',
        ],
    ]);
});

it('persists keyword_ids, h2_sections with uuids, and validated target_id', function () {
    $cluster = ContentCluster::create(['name' => 'Cluster', 'locale' => 'nl', 'content_type' => 'blog']);
    $kw1 = Keyword::create(['keyword' => 'k1', 'locale' => 'nl', 'status' => 'approved', 'volume_exact' => 1000]);
    $kw2 = Keyword::create(['keyword' => 'k2', 'locale' => 'nl', 'status' => 'approved', 'volume_exact' => 300]);
    $cluster->keywords()->attach([$kw1->id, $kw2->id]);

    $existing = FakeRouteModelForConcepts::create(['name' => ['nl' => 'Bestaand'], 'slug' => ['nl' => 'bestaand']]);

    Ai::shouldReceive('json')->once()->andReturn([
        'concepts' => [
            [
                'title' => 'Nieuw concept',
                'description' => 'desc',
                'suggested_target_type' => 'fakeRouteModelForConcepts',
                'target_mode' => 'new',
                'target_id' => null,
                'keyword_ids' => [$kw1->id, $kw2->id, 9999],
                'h2_sections' => [
                    ['heading' => 'H1 sec', 'intent' => 'wat het doet'],
                    ['heading' => 'H2 sec', 'intent' => 'nog meer'],
                ],
            ],
            [
                'title' => 'Overschrijf bestaand',
                'description' => 'desc',
                'suggested_target_type' => 'fakeRouteModelForConcepts',
                'target_mode' => 'overwrite',
                'target_id' => $existing->id,
                'keyword_ids' => [$kw1->id],
                'h2_sections' => [['heading' => 'Enig', 'intent' => 'enig']],
            ],
        ],
    ]);

    (new GenerateClusterConceptsJob($cluster->id, 2, null))->handle();

    $cluster->refresh();
    $concepts = $cluster->pending_concepts;

    expect($concepts)->toHaveCount(2);

    expect($concepts[0]['keyword_ids'])->toEqualCanonicalizing([$kw1->id, $kw2->id])
        ->and($concepts[0]['h2_sections'])->toHaveCount(2)
        ->and($concepts[0]['h2_sections'][0]['id'])->not->toBeEmpty()
        ->and($concepts[0]['target_mode'])->toBe('new')
        ->and($concepts[0]['target_id'])->toBeNull();

    expect($concepts[1]['target_id'])->toBe($existing->id)
        ->and($concepts[1]['target_mode'])->toBe('overwrite')
        ->and($concepts[1]['target_preview_name'])->toBe('Bestaand');
});

it('drops concepts with empty keyword_ids or zero h2_sections', function () {
    $cluster = ContentCluster::create(['name' => 'Cluster', 'locale' => 'nl', 'content_type' => 'blog']);
    $kw1 = Keyword::create(['keyword' => 'k1', 'locale' => 'nl', 'status' => 'approved']);
    $cluster->keywords()->attach($kw1->id);

    Ai::shouldReceive('json')->once()->andReturn([
        'concepts' => [
            [
                'title' => 'Invalid keywords',
                'description' => '',
                'suggested_target_type' => 'fakeRouteModelForConcepts',
                'target_mode' => 'new',
                'target_id' => null,
                'keyword_ids' => [9999],
                'h2_sections' => [['heading' => 'x', 'intent' => 'y']],
            ],
            [
                'title' => 'Geen secties',
                'description' => '',
                'suggested_target_type' => 'fakeRouteModelForConcepts',
                'target_mode' => 'new',
                'target_id' => null,
                'keyword_ids' => [$kw1->id],
                'h2_sections' => [],
            ],
            [
                'title' => 'Geldig',
                'description' => '',
                'suggested_target_type' => 'fakeRouteModelForConcepts',
                'target_mode' => 'new',
                'target_id' => null,
                'keyword_ids' => [$kw1->id],
                'h2_sections' => [['heading' => 'ok', 'intent' => 'prima']],
            ],
        ],
    ]);

    (new GenerateClusterConceptsJob($cluster->id, 3, null))->handle();

    $cluster->refresh();
    expect($cluster->pending_concepts)->toHaveCount(1)
        ->and($cluster->pending_concepts[0]['title'])->toBe('Geldig');
});

<?php

use Dashed\DashedAi\Facades\Ai;
use Dashed\DashedMarketing\Jobs\GenerateSeoAuditJob;
use Dashed\DashedMarketing\Models\SeoAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

class FakeSeoAuditIntegrationSubject extends Model
{
    protected $table = 'fake_seo_audit_integration_subjects';
    protected $guarded = [];
    public $timestamps = true;
    protected $casts = ['name' => 'array', 'slug' => 'array'];
}

beforeEach(function () {
    Schema::dropIfExists('fake_seo_audit_integration_subjects');
    Schema::create('fake_seo_audit_integration_subjects', function (Blueprint $table) {
        $table->id();
        $table->json('name')->nullable();
        $table->json('slug')->nullable();
        $table->timestamps();
    });

    cms()->builder('routeModels', [
        'fakeSeoIntegrationSubject' => [
            'name' => 'Fake',
            'class' => FakeSeoAuditIntegrationSubject::class,
        ],
    ]);
});

it('runs all 7 steps and finalises audit to ready with populated relations', function () {
    $subject = FakeSeoAuditIntegrationSubject::create([
        'name' => ['nl' => 'Test subject'],
        'slug' => ['nl' => 'test-subject'],
    ]);

    Ai::shouldReceive('json')->times(7)->andReturnUsing(function ($prompt) {
        if (str_contains($prompt, 'headings_structure')) {
            return [
                'summary' => 'page ok',
                'headings_structure' => [['level' => 1, 'text' => 'Titel']],
                'content_length' => 500,
                'keyword_density' => ['test' => 2.0],
                'alt_text_coverage' => ['total' => 0, 'with_alt' => 0, 'missing' => 0],
                'readability_score' => 70,
                'notes' => 'looks good',
            ];
        }
        if (str_contains($prompt, 'keyword-research') || str_contains($prompt, 'keyword research')) {
            return ['summary' => 'kw', 'suggestions' => [
                ['keyword' => 'test-lsi', 'type' => 'lsi', 'priority' => 'high'],
            ]];
        }
        if (str_contains($prompt, 'meta-verbeteringen voor voor')) {
            return ['summary' => 'meta', 'suggestions' => [
                ['field' => 'meta_title', 'suggested_value' => 'X', 'priority' => 'high'],
            ]];
        }
        if (str_contains($prompt, 'content-blokken voor betere SEO')) {
            return ['summary' => 'b', 'suggestions' => []];
        }
        if (str_contains($prompt, 'FAQ items voor')) {
            return ['summary' => 'f', 'suggestions' => [
                ['question' => 'Wat?', 'answer' => 'Dit.', 'priority' => 'medium'],
            ]];
        }
        if (str_contains($prompt, 'JSON-LD structured data')) {
            return ['summary' => 'sd', 'suggestions' => [
                ['schema_type' => 'FAQPage', 'json_ld' => '{"@context":"https://schema.org","@type":"FAQPage"}', 'priority' => 'high'],
            ]];
        }
        if (str_contains($prompt, 'interne links voor op')) {
            return ['summary' => 'il', 'suggestions' => []];
        }

        return [];
    });

    (new GenerateSeoAuditJob(FakeSeoAuditIntegrationSubject::class, $subject->id))->handle();

    $audit = SeoAudit::where('subject_type', FakeSeoAuditIntegrationSubject::class)
        ->where('subject_id', $subject->id)
        ->where('status', '!=', 'archived')
        ->first();

    expect($audit)->not->toBeNull();
    expect($audit->status)->toBe('ready');
    expect($audit->overall_score)->toBeGreaterThan(0);
    expect($audit->metaSuggestions)->toHaveCount(1);
    expect($audit->faqSuggestions)->toHaveCount(1);
    expect($audit->structuredDataSuggestions)->toHaveCount(1);
    expect($audit->keywords)->toHaveCount(1);
    expect($audit->pageAnalysis)->not->toBeNull();
    expect($audit->pageAnalysis->readability_score)->toBe(70);
});

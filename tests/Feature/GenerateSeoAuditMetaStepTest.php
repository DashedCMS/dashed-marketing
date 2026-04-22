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

class FakeSeoAuditMetaStepSubject extends Model
{
    protected $table = 'fake_seo_audit_meta_subjects';
    protected $guarded = [];
    public $timestamps = true;
    protected $casts = ['name' => 'array', 'slug' => 'array'];
}

beforeEach(function () {
    Schema::dropIfExists('fake_seo_audit_meta_subjects');
    Schema::create('fake_seo_audit_meta_subjects', function (Blueprint $table) {
        $table->id();
        $table->json('name')->nullable();
        $table->json('slug')->nullable();
        $table->timestamps();
    });

    cms()->builder('routeModels', [
        'fakeSeoMetaSubject' => [
            'name' => 'Fake',
            'class' => FakeSeoAuditMetaStepSubject::class,
        ],
    ]);
});

it('writes meta suggestions from the AI response and filters unknown fields', function () {
    $subject = FakeSeoAuditMetaStepSubject::create([
        'name' => ['nl' => 'Test subject'],
        'slug' => ['nl' => 'test-subject'],
    ]);

    // Seven Ai::json calls. Six return empty; the meta step (3rd call) returns two valid + one invalid field.
    Ai::shouldReceive('json')->andReturnUsing(function ($prompt) {
        if (str_contains($prompt, 'meta-verbeteringen voor voor')) {
            return [
                'summary' => 'meta ok',
                'suggestions' => [
                    ['field' => 'meta_title', 'suggested_value' => 'Beter meta title', 'reason' => 'x', 'priority' => 'high'],
                    ['field' => 'meta_description', 'suggested_value' => 'Beter meta description', 'reason' => 'y', 'priority' => 'medium'],
                    ['field' => 'invalid_field', 'suggested_value' => 'skip', 'reason' => 'z', 'priority' => 'low'],
                ],
            ];
        }

        return ['summary' => '', 'suggestions' => []];
    });

    (new GenerateSeoAuditJob(FakeSeoAuditMetaStepSubject::class, $subject->id))->handle();

    $audit = SeoAudit::where('subject_type', FakeSeoAuditMetaStepSubject::class)
        ->where('subject_id', $subject->id)
        ->where('status', '!=', 'archived')
        ->first();

    expect($audit)->not->toBeNull();
    expect($audit->metaSuggestions)->toHaveCount(2);
    expect($audit->metaSuggestions->pluck('field')->all())
        ->toContain('meta_title')
        ->toContain('meta_description')
        ->not->toContain('invalid_field');
});

<?php

use Dashed\DashedMarketing\Models\ContentApplyLog;
use Dashed\DashedMarketing\Models\SeoAudit;
use Dashed\DashedMarketing\Models\SeoImprovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

class FakeMigrateSeoSubject extends Model
{
    protected $table = 'fake_migrate_seo_subjects';
    protected $guarded = [];
    public $timestamps = true;
}

beforeEach(function () {
    Schema::dropIfExists('fake_migrate_seo_subjects');
    Schema::create('fake_migrate_seo_subjects', function (Blueprint $table) {
        $table->id();
        $table->timestamps();
    });
});

it('converts legacy SeoImprovement into SeoAudit with meta + block suggestions + back-fills apply logs', function () {
    $subject = FakeMigrateSeoSubject::create([]);

    $imp = SeoImprovement::create([
        'subject_type' => FakeMigrateSeoSubject::class,
        'subject_id' => $subject->id,
        'status' => 'applied',
        'analysis_summary' => 'oud',
        'field_proposals' => ['meta_title' => 'Old title', 'unknown_field' => 'skip'],
        'block_proposals' => ['blockA' => ['content' => 'old block']],
    ]);

    ContentApplyLog::create([
        'seo_improvement_id' => $imp->id,
        'subject_type' => FakeMigrateSeoSubject::class,
        'subject_id' => $subject->id,
        'field_key' => 'meta.meta_title',
        'previous_value' => '"x"',
        'new_value' => '"y"',
        'applied_by' => 1,
        'applied_at' => now(),
    ]);

    $this->artisan('dashed-marketing:migrate-seo-improvements')->assertSuccessful();

    $audit = SeoAudit::where('subject_type', FakeMigrateSeoSubject::class)
        ->where('subject_id', $subject->id)
        ->first();
    expect($audit)->not->toBeNull();
    expect($audit->status)->toBe('archived');
    expect($audit->analysis_summary)->toBe('oud');
    expect($audit->metaSuggestions()->where('field', 'meta_title')->count())->toBe(1);
    expect($audit->metaSuggestions()->where('field', 'unknown_field')->count())->toBe(0);
    expect($audit->blockSuggestions()->count())->toBe(1);

    $log = ContentApplyLog::where('seo_improvement_id', $imp->id)->first();
    expect($log->audit_id)->toBe($audit->id);
});

it('is idempotent — rerunning does not create duplicates', function () {
    $subject = FakeMigrateSeoSubject::create([]);
    SeoImprovement::create([
        'subject_type' => FakeMigrateSeoSubject::class,
        'subject_id' => $subject->id,
        'status' => 'ready',
        'field_proposals' => ['meta_title' => 'X'],
    ]);

    $this->artisan('dashed-marketing:migrate-seo-improvements')->assertSuccessful();
    $this->artisan('dashed-marketing:migrate-seo-improvements')->assertSuccessful();

    expect(SeoAudit::where('subject_type', FakeMigrateSeoSubject::class)->count())->toBe(1);
});

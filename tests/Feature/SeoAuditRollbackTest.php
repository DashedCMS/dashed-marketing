<?php

use Dashed\DashedCore\Models\Concerns\HasMetadata;
use Dashed\DashedMarketing\Models\SeoAudit;
use Dashed\DashedMarketing\Models\SeoAuditMetaSuggestion;
use Dashed\DashedMarketing\Services\SeoAuditApplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Translatable\HasTranslations;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

class FakeRollbackSubject extends Model
{
    use HasMetadata;
    use HasTranslations;

    protected $table = 'fake_rollback_subjects';
    protected $guarded = [];
    public $timestamps = true;
    public $translatable = ['name'];
    protected $casts = ['name' => 'array'];
}

beforeEach(function () {
    Schema::dropIfExists('fake_rollback_subjects');
    Schema::create('fake_rollback_subjects', function (Blueprint $table) {
        $table->id();
        $table->json('name')->nullable();
        $table->timestamps();
    });
});

it('rollbackAudit restores meta_title to the pre-apply value', function () {
    $subject = FakeRollbackSubject::create([]);
    $subject->metadata()->create(['title' => ['nl' => 'Oud']]);

    $audit = SeoAudit::create([
        'subject_type' => FakeRollbackSubject::class,
        'subject_id' => $subject->id,
        'status' => 'ready',
        'locale' => 'nl',
    ]);
    $meta = SeoAuditMetaSuggestion::create([
        'audit_id' => $audit->id,
        'field' => 'meta_title',
        'suggested_value' => 'Nieuw',
        'priority' => 'high',
        'status' => 'pending',
    ]);

    app(SeoAuditApplier::class)->applySelected($audit, ['meta' => [$meta->id]], userId: 1);
    expect($subject->fresh()->metadata->getTranslation('title', 'nl'))->toBe('Nieuw');

    app(SeoAuditApplier::class)->rollbackAudit($audit->fresh());
    expect($subject->fresh()->metadata->getTranslation('title', 'nl'))->toBe('Oud');
});

it('rollbackAudit restores subject field (name) to previous', function () {
    $subject = FakeRollbackSubject::create([]);
    $subject->setTranslation('name', 'nl', 'Oude naam');
    $subject->save();

    $audit = SeoAudit::create([
        'subject_type' => FakeRollbackSubject::class,
        'subject_id' => $subject->id,
        'status' => 'ready',
        'locale' => 'nl',
    ]);
    $sug = SeoAuditMetaSuggestion::create([
        'audit_id' => $audit->id,
        'field' => 'name',
        'suggested_value' => 'Nieuwe naam',
        'priority' => 'medium',
        'status' => 'pending',
    ]);

    app(SeoAuditApplier::class)->applySelected($audit, ['meta' => [$sug->id]], userId: 1);
    expect($subject->fresh()->getTranslation('name', 'nl'))->toBe('Nieuwe naam');

    app(SeoAuditApplier::class)->rollbackAudit($audit->fresh());
    expect($subject->fresh()->getTranslation('name', 'nl'))->toBe('Oude naam');
});

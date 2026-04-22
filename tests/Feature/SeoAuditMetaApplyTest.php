<?php

use Dashed\DashedCore\Models\Concerns\HasMetadata;
use Dashed\DashedMarketing\Models\ContentApplyLog;
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

class FakeMetaApplySubject extends Model
{
    use HasMetadata;
    use HasTranslations;

    protected $table = 'fake_meta_apply_subjects';
    protected $guarded = [];
    public $timestamps = true;
    public $translatable = ['name', 'slug', 'excerpt'];
    protected $casts = ['name' => 'array', 'slug' => 'array', 'excerpt' => 'array'];
}

beforeEach(function () {
    Schema::dropIfExists('fake_meta_apply_subjects');
    Schema::create('fake_meta_apply_subjects', function (Blueprint $table) {
        $table->id();
        $table->json('name')->nullable();
        $table->json('slug')->nullable();
        $table->json('excerpt')->nullable();
        $table->timestamps();
    });
});

it('applies meta_title suggestion to subject metadata for locale and logs it', function () {
    $subject = FakeMetaApplySubject::create([]);
    $subject->setTranslation('name', 'nl', 'Oude naam');
    $subject->save();

    $audit = SeoAudit::create([
        'subject_type' => FakeMetaApplySubject::class,
        'subject_id' => $subject->id,
        'status' => 'ready',
        'locale' => 'nl',
    ]);

    $meta = SeoAuditMetaSuggestion::create([
        'audit_id' => $audit->id,
        'field' => 'meta_title',
        'suggested_value' => 'Nieuwe SEO title',
        'priority' => 'high',
        'status' => 'pending',
    ]);

    $result = app(SeoAuditApplier::class)->applySelected(
        $audit,
        ['meta' => [$meta->id]],
        userId: 1,
    );

    expect($result->applied)->toBe(1);
    expect($meta->fresh()->status)->toBe('applied');

    $subject->refresh();
    expect($subject->metadata)->not->toBeNull();
    expect($subject->metadata->getTranslation('title', 'nl'))->toBe('Nieuwe SEO title');

    $log = ContentApplyLog::where('audit_id', $audit->id)->first();
    expect($log)->not->toBeNull();
    expect($log->field_key)->toBe('meta.meta_title');
    expect($log->applied_by)->toBe(1);

    $audit->refresh();
    expect($audit->status)->toBe('fully_applied');
});

it('applies translatable subject field (name) and logs previous value', function () {
    $subject = FakeMetaApplySubject::create([]);
    $subject->setTranslation('name', 'nl', 'Oude naam');
    $subject->save();

    $audit = SeoAudit::create([
        'subject_type' => FakeMetaApplySubject::class,
        'subject_id' => $subject->id,
        'status' => 'ready',
        'locale' => 'nl',
    ]);

    $meta = SeoAuditMetaSuggestion::create([
        'audit_id' => $audit->id,
        'field' => 'name',
        'suggested_value' => 'Betere naam',
        'priority' => 'medium',
        'status' => 'pending',
    ]);

    $result = app(SeoAuditApplier::class)->applySelected($audit, ['meta' => [$meta->id]], userId: 1);

    expect($result->applied)->toBe(1);
    $subject->refresh();
    expect($subject->getTranslation('name', 'nl'))->toBe('Betere naam');

    $log = ContentApplyLog::where('audit_id', $audit->id)->first();
    expect($log->field_key)->toBe('meta.name');
    expect(json_decode($log->previous_value, true))->toBe('Oude naam');
});

it('skips already-applied suggestions', function () {
    $subject = FakeMetaApplySubject::create([]);
    $audit = SeoAudit::create([
        'subject_type' => FakeMetaApplySubject::class,
        'subject_id' => $subject->id,
        'status' => 'ready',
        'locale' => 'nl',
    ]);
    $meta = SeoAuditMetaSuggestion::create([
        'audit_id' => $audit->id,
        'field' => 'meta_title',
        'suggested_value' => 'X',
        'priority' => 'high',
        'status' => 'applied',
    ]);

    $result = app(SeoAuditApplier::class)->applySelected($audit, ['meta' => [$meta->id]], userId: 1);

    expect($result->applied)->toBe(0);
    expect($result->skipped)->toBe(1);
});

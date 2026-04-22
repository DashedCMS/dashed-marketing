<?php

use Dashed\DashedCore\Models\CustomStructuredData;
use Dashed\DashedMarketing\Models\ContentApplyLog;
use Dashed\DashedMarketing\Models\SeoAudit;
use Dashed\DashedMarketing\Models\SeoAuditStructuredDataSuggestion;
use Dashed\DashedMarketing\Services\SeoAuditApplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

class FakeStructuredDataApplySubject extends Model
{
    protected $table = 'fake_structured_data_apply_subjects';
    protected $guarded = [];
    public $timestamps = true;
}

beforeEach(function () {
    Schema::dropIfExists('fake_structured_data_apply_subjects');
    Schema::create('fake_structured_data_apply_subjects', function (Blueprint $table) {
        $table->id();
        $table->timestamps();
    });
});

it('upserts structured data per schema type', function () {
    $subject = FakeStructuredDataApplySubject::create([]);
    $audit = SeoAudit::create([
        'subject_type' => FakeStructuredDataApplySubject::class,
        'subject_id' => $subject->id,
        'status' => 'ready',
        'locale' => 'nl',
    ]);
    $sug = SeoAuditStructuredDataSuggestion::create([
        'audit_id' => $audit->id,
        'schema_type' => 'FAQPage',
        'json_ld' => '{"@context":"https://schema.org","@type":"FAQPage"}',
        'priority' => 'high',
        'status' => 'pending',
    ]);

    $res = app(SeoAuditApplier::class)->applySelected($audit, ['structured_data' => [$sug->id]], userId: 1);

    expect($res->applied)->toBe(1);
    expect(CustomStructuredData::where('subject_type', FakeStructuredDataApplySubject::class)
        ->where('subject_id', $subject->id)
        ->where('schema_type', 'FAQPage')
        ->count())->toBe(1);

    $log = ContentApplyLog::where('audit_id', $audit->id)->first();
    expect($log->field_key)->toBe('structured_data.FAQPage');
});

it('replaces existing structured data of same schema type', function () {
    $subject = FakeStructuredDataApplySubject::create([]);

    CustomStructuredData::create([
        'subject_type' => FakeStructuredDataApplySubject::class,
        'subject_id' => $subject->id,
        'schema_type' => 'FAQPage',
        'json_ld' => '{"@type":"FAQPage","old":true}',
    ]);

    $audit = SeoAudit::create([
        'subject_type' => FakeStructuredDataApplySubject::class,
        'subject_id' => $subject->id,
        'status' => 'ready',
        'locale' => 'nl',
    ]);
    $sug = SeoAuditStructuredDataSuggestion::create([
        'audit_id' => $audit->id,
        'schema_type' => 'FAQPage',
        'json_ld' => '{"@context":"https://schema.org","@type":"FAQPage","new":true}',
        'priority' => 'high',
        'status' => 'pending',
    ]);

    app(SeoAuditApplier::class)->applySelected($audit, ['structured_data' => [$sug->id]], userId: 1);

    $count = CustomStructuredData::where('subject_type', FakeStructuredDataApplySubject::class)
        ->where('subject_id', $subject->id)
        ->where('schema_type', 'FAQPage')
        ->count();
    expect($count)->toBe(1);

    $current = CustomStructuredData::where('subject_type', FakeStructuredDataApplySubject::class)
        ->where('subject_id', $subject->id)
        ->where('schema_type', 'FAQPage')
        ->first();
    expect($current->json_ld)->toContain('"new":true');
});

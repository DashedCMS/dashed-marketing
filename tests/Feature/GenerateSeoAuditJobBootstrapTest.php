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

// Minimal visitable stand-in for tests (matches pattern from ContentDraftPublishTest.php).
class FakeSeoAuditSubject extends Model
{
    protected $table = 'fake_seo_audit_subjects';
    protected $guarded = [];
    public $timestamps = true;
    protected $casts = ['name' => 'array', 'slug' => 'array'];
}

beforeEach(function () {
    Schema::dropIfExists('fake_seo_audit_subjects');
    Schema::create('fake_seo_audit_subjects', function (Blueprint $table) {
        $table->id();
        $table->json('name')->nullable();
        $table->json('slug')->nullable();
        $table->timestamps();
    });

    cms()->builder('routeModels', [
        'fakeSeoAuditSubject' => [
            'name' => 'Fake SEO Subject',
            'pluralName' => 'Fake SEO Subjects',
            'class' => FakeSeoAuditSubject::class,
            'nameField' => 'name',
        ],
    ]);
});

it('archives an existing active audit before creating a new one', function () {
    $subject = FakeSeoAuditSubject::create([
        'name' => ['nl' => 'Test subject'],
        'slug' => ['nl' => 'test-subject'],
    ]);

    $old = SeoAudit::create([
        'subject_type' => FakeSeoAuditSubject::class,
        'subject_id' => $subject->id,
        'status' => 'ready',
        'locale' => 'nl',
    ]);

    // All 7 step prompts result in an Ai::json call. Stub returns empty suggestions
    // so the job finishes all steps without side effects, then finalises.
    Ai::shouldReceive('json')->andReturn(['summary' => '', 'suggestions' => []]);

    (new GenerateSeoAuditJob(FakeSeoAuditSubject::class, $subject->id))->handle();

    $old->refresh();
    expect($old->status)->toBe('archived');
    expect($old->archived_at)->not->toBeNull();

    $new = SeoAudit::where('subject_type', FakeSeoAuditSubject::class)
        ->where('subject_id', $subject->id)
        ->where('status', '!=', 'archived')
        ->first();
    expect($new)->not->toBeNull();
    expect($new->id)->not->toBe($old->id);
    expect($new->status)->toBe('ready'); // finalised after all (empty) steps
});

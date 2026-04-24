<?php

use Dashed\DashedAi\Facades\Ai;
use Dashed\DashedCore\Models\Concerns\HasCustomBlocks;
use Dashed\DashedMarketing\Jobs\GenerateOutlineContentJob;
use Dashed\DashedMarketing\Models\SeoAudit;
use Dashed\DashedMarketing\Models\SeoAuditBlockSuggestion;
use Dashed\DashedMarketing\Models\SeoAuditOutline;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Translatable\HasTranslations;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

class FakeOutlineJobSubject extends Model
{
    use HasCustomBlocks;
    use HasTranslations;

    protected $table = 'fake_outline_job_subjects';
    protected $guarded = [];
    public $timestamps = true;
    public $translatable = ['name'];
    protected $casts = ['name' => 'array'];
}

beforeEach(function () {
    Schema::dropIfExists('fake_outline_job_subjects');
    Schema::create('fake_outline_job_subjects', function (Blueprint $table) {
        $table->id();
        $table->json('name')->nullable();
        $table->timestamps();
    });
});

function makeAuditWithOutline(array $headings): SeoAudit
{
    $subject = FakeOutlineJobSubject::create([]);

    $audit = SeoAudit::create([
        'subject_type' => FakeOutlineJobSubject::class,
        'subject_id' => $subject->id,
        'status' => 'ready',
        'locale' => 'nl',
    ]);

    SeoAuditOutline::create([
        'audit_id' => $audit->id,
        'h1' => 'Test H1',
        'summary' => 'Korte context',
        'headings' => $headings,
        'content_generating_at' => now(),
    ]);

    return $audit->fresh('outline');
}

it('creates block suggestions for every non-empty heading', function () {
    $audit = makeAuditWithOutline([
        ['level' => 2, 'text' => 'Eerste heading'],
        ['level' => 3, 'text' => 'Subheading'],
        ['level' => 2, 'text' => 'Tweede heading'],
    ]);

    Ai::shouldReceive('json')
        ->times(3)
        ->andReturn(
            ['body' => '<p>Body een</p>'],
            ['body' => '<p>Body twee</p>'],
            ['body' => '<p>Body drie</p>'],
        );

    (new GenerateOutlineContentJob($audit->id))->handle();

    $suggestions = SeoAuditBlockSuggestion::where('audit_id', $audit->id)
        ->where('is_new_block', true)
        ->orderBy('block_key')
        ->get();

    expect($suggestions)->toHaveCount(3);

    expect($suggestions[0]->block_key)->toBe('outline.0');
    expect($suggestions[0]->suggested_value)->toBe('<h2>Eerste heading</h2><p>Body een</p>');
    expect($suggestions[0]->status)->toBe('pending');
    expect($suggestions[0]->field_key)->toBe('_new');
    expect($suggestions[0]->block_type)->toBe('content');

    expect($suggestions[1]->block_key)->toBe('outline.1');
    expect($suggestions[1]->suggested_value)->toBe('<h3>Subheading</h3><p>Body twee</p>');

    expect($suggestions[2]->block_key)->toBe('outline.2');
    expect($suggestions[2]->suggested_value)->toBe('<h2>Tweede heading</h2><p>Body drie</p>');

    $outline = $audit->outline->fresh();
    expect($outline->content_generating_at)->toBeNull();
    expect($outline->content_generated_at)->not->toBeNull();
});

it('still creates suggestion with only heading html when AI returns empty', function () {
    $audit = makeAuditWithOutline([
        ['level' => 2, 'text' => 'Heading zonder body'],
        ['level' => 2, 'text' => 'Tweede heading'],
    ]);

    Ai::shouldReceive('json')
        ->times(2)
        ->andReturn(null, []);

    (new GenerateOutlineContentJob($audit->id))->handle();

    $suggestions = SeoAuditBlockSuggestion::where('audit_id', $audit->id)
        ->orderBy('block_key')
        ->get();

    expect($suggestions)->toHaveCount(2);
    expect($suggestions[0]->suggested_value)->toBe('<h2>Heading zonder body</h2>');
    expect($suggestions[1]->suggested_value)->toBe('<h2>Tweede heading</h2>');
});

it('clears content_generating_at and creates nothing when headings are empty', function () {
    $audit = makeAuditWithOutline([]);

    Ai::shouldReceive('json')->never();

    (new GenerateOutlineContentJob($audit->id))->handle();

    expect(SeoAuditBlockSuggestion::where('audit_id', $audit->id)->count())->toBe(0);
    expect($audit->outline->fresh()->content_generating_at)->toBeNull();
});

it('deletes previous is_new_block suggestions but keeps is_new_block=false ones', function () {
    $audit = makeAuditWithOutline([
        ['level' => 2, 'text' => 'Nieuwe heading'],
    ]);

    $stayingSuggestion = SeoAuditBlockSuggestion::create([
        'audit_id' => $audit->id,
        'block_index' => 0,
        'block_key' => null,
        'block_type' => 'content',
        'field_key' => 'content',
        'is_new_block' => false,
        'suggested_value' => '<p>Bestaande update</p>',
        'status' => 'pending',
        'priority' => 'medium',
    ]);

    $leavingSuggestion = SeoAuditBlockSuggestion::create([
        'audit_id' => $audit->id,
        'block_index' => null,
        'block_key' => 'outline.99',
        'block_type' => 'content',
        'field_key' => '_new',
        'is_new_block' => true,
        'suggested_value' => '<h2>Oude</h2><p>Oud</p>',
        'status' => 'pending',
        'priority' => 'medium',
    ]);

    Ai::shouldReceive('json')->once()->andReturn(['body' => '<p>Nieuw</p>']);

    (new GenerateOutlineContentJob($audit->id))->handle();

    expect(SeoAuditBlockSuggestion::find($stayingSuggestion->id))->not->toBeNull();
    expect(SeoAuditBlockSuggestion::find($leavingSuggestion->id))->toBeNull();

    expect(SeoAuditBlockSuggestion::where('audit_id', $audit->id)->where('is_new_block', true)->count())->toBe(1);
});

it('returns silently when audit does not exist', function () {
    Ai::shouldReceive('json')->never();

    (new GenerateOutlineContentJob(99999))->handle();

    expect(true)->toBeTrue(); // reached without exception
});

it('clears content_generating_at in failed() hook', function () {
    $audit = makeAuditWithOutline([
        ['level' => 2, 'text' => 'Heading'],
    ]);

    expect($audit->outline->content_generating_at)->not->toBeNull();

    (new GenerateOutlineContentJob($audit->id))->failed(new \RuntimeException('boom'));

    expect($audit->outline->fresh()->content_generating_at)->toBeNull();
});

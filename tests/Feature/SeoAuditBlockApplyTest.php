<?php

use Dashed\DashedCore\Models\Concerns\HasCustomBlocks;
use Dashed\DashedCore\Models\CustomBlock;
use Dashed\DashedMarketing\Models\ContentApplyLog;
use Dashed\DashedMarketing\Models\SeoAudit;
use Dashed\DashedMarketing\Models\SeoAuditBlockSuggestion;
use Dashed\DashedMarketing\Services\SeoAuditApplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Translatable\HasTranslations;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

class FakeBlockApplySubject extends Model
{
    use HasCustomBlocks;
    use HasTranslations;

    protected $table = 'fake_block_apply_subjects';
    protected $guarded = [];
    public $timestamps = true;
    public $translatable = ['name'];
    protected $casts = ['name' => 'array'];
}

beforeEach(function () {
    Schema::dropIfExists('fake_block_apply_subjects');
    Schema::create('fake_block_apply_subjects', function (Blueprint $table) {
        $table->id();
        $table->json('name')->nullable();
        $table->timestamps();
    });
});

it('merges block field suggestion without touching other fields', function () {
    $subject = FakeBlockApplySubject::create([]);

    $cb = new CustomBlock();
    $cb->blockable_type = FakeBlockApplySubject::class;
    $cb->blockable_id = $subject->id;
    $cb->setTranslation('blocks', 'nl', [
        ['type' => 'content', 'data' => ['content' => '<p>Oud</p>', 'in_container' => true, 'top_margin' => true]],
    ]);
    $cb->save();

    $audit = SeoAudit::create([
        'subject_type' => FakeBlockApplySubject::class,
        'subject_id' => $subject->id,
        'status' => 'ready',
        'locale' => 'nl',
    ]);

    $sug = SeoAuditBlockSuggestion::create([
        'audit_id' => $audit->id,
        'block_index' => 0,
        'block_type' => 'content',
        'field_key' => 'content',
        'is_new_block' => false,
        'suggested_value' => '<p>Nieuw</p>',
        'status' => 'pending',
        'priority' => 'high',
    ]);

    $result = app(SeoAuditApplier::class)->applySelected($audit, ['blocks' => [$sug->id]], userId: 1);

    expect($result->applied)->toBe(1);

    $subject->refresh();
    $blocks = $subject->customBlocks->getTranslation('blocks', 'nl');
    expect($blocks[0]['data']['content'])->toBe('<p>Nieuw</p>');
    expect($blocks[0]['data']['in_container'])->toBe(true); // other field untouched

    $log = ContentApplyLog::where('audit_id', $audit->id)->first();
    expect($log)->not->toBeNull();
    expect($log->field_key)->toBe('block.0.content');
});

it('appends a new block when is_new_block is true', function () {
    $subject = FakeBlockApplySubject::create([]);
    $audit = SeoAudit::create([
        'subject_type' => FakeBlockApplySubject::class,
        'subject_id' => $subject->id,
        'status' => 'ready',
        'locale' => 'nl',
    ]);

    $sug = SeoAuditBlockSuggestion::create([
        'audit_id' => $audit->id,
        'block_index' => null,
        'block_type' => 'cta',
        'field_key' => '_new',
        'is_new_block' => true,
        'suggested_value' => json_encode(['title' => 'Koop nu', 'subtitle' => 'Laatste kans']),
        'status' => 'pending',
        'priority' => 'high',
    ]);

    $result = app(SeoAuditApplier::class)->applySelected($audit, ['blocks' => [$sug->id]], userId: 1);

    expect($result->applied)->toBe(1);

    $subject->refresh();
    $blocks = $subject->customBlocks->getTranslation('blocks', 'nl');
    expect($blocks)->toHaveCount(1);
    expect($blocks[0]['type'])->toBe('cta');
    expect($blocks[0]['data']['title'])->toBe('Koop nu');
    expect($blocks[0]['data']['in_container'])->toBe(true);
    expect($blocks[0]['data']['top_margin'])->toBe(true);
});

it('marks suggestion failed when block_index no longer matches', function () {
    $subject = FakeBlockApplySubject::create([]);
    $audit = SeoAudit::create([
        'subject_type' => FakeBlockApplySubject::class,
        'subject_id' => $subject->id,
        'status' => 'ready',
        'locale' => 'nl',
    ]);

    $sug = SeoAuditBlockSuggestion::create([
        'audit_id' => $audit->id,
        'block_index' => 5,
        'block_type' => 'content',
        'field_key' => 'content',
        'is_new_block' => false,
        'suggested_value' => '<p>X</p>',
        'status' => 'pending',
        'priority' => 'high',
    ]);

    $result = app(SeoAuditApplier::class)->applySelected($audit, ['blocks' => [$sug->id]], userId: 1);

    expect($result->applied)->toBe(0);
    expect($result->failed)->toBe(1);
    expect($sug->fresh()->status)->toBe('failed');
});

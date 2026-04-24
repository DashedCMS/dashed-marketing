<?php

use Dashed\DashedCore\Models\Concerns\HasCustomBlocks;
use Dashed\DashedCore\Models\CustomBlock;
use Dashed\DashedMarketing\Models\ContentApplyLog;
use Dashed\DashedMarketing\Models\SeoAudit;
use Dashed\DashedMarketing\Models\SeoAuditBlockSuggestion;
use Dashed\DashedMarketing\Models\SeoAuditMetaSuggestion;
use Dashed\DashedMarketing\Services\SeoAuditApplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Translatable\HasTranslations;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

class FakeReapplySubject extends Model
{
    use HasCustomBlocks;
    use HasTranslations;

    protected $table = 'fake_reapply_subjects';
    protected $guarded = [];
    public $timestamps = true;
    public $translatable = ['name'];
    protected $casts = ['name' => 'array'];
}

beforeEach(function () {
    Schema::dropIfExists('fake_reapply_subjects');
    Schema::create('fake_reapply_subjects', function (Blueprint $table) {
        $table->id();
        $table->json('name')->nullable();
        $table->timestamps();
    });
});

function makeReapplyAudit(): array
{
    $subject = FakeReapplySubject::create([]);
    $audit = SeoAudit::create([
        'subject_type' => FakeReapplySubject::class,
        'subject_id' => $subject->id,
        'status' => 'ready',
        'locale' => 'nl',
    ]);

    return [$audit, $subject];
}

it('overwrites the previously-created block on re-apply of is_new_block=true suggestion', function () {
    [$audit, $subject] = makeReapplyAudit();

    $sug = SeoAuditBlockSuggestion::create([
        'audit_id' => $audit->id,
        'block_index' => null,
        'block_key' => 'outline.0',
        'block_type' => 'content',
        'field_key' => '_new',
        'is_new_block' => true,
        'suggested_value' => '<h2>Eerste</h2><p>Body een</p>',
        'status' => 'pending',
        'priority' => 'medium',
    ]);

    app(SeoAuditApplier::class)->applySelected($audit, ['blocks' => [$sug->id]], userId: 1);

    $subject->refresh();
    $blocks = $subject->customBlocks->getTranslation('blocks', 'nl');
    expect($blocks)->toHaveCount(1);
    expect($blocks[0]['data']['content'])->toBe('<h2>Eerste</h2><p>Body een</p>');

    $sug->refresh();
    expect($sug->status)->toBe('applied');
    expect($sug->applied_block_index)->toBe(0);

    $sug->update([
        'suggested_value' => '<h2>Aangepast</h2><p>Nieuw body</p>',
        'status' => 'applied',
    ]);

    app(SeoAuditApplier::class)->applySelected($audit, ['blocks' => [$sug->id]], userId: 1);

    $subject->refresh();
    $blocks = $subject->customBlocks->getTranslation('blocks', 'nl');

    expect($blocks)->toHaveCount(1);
    expect($blocks[0]['data']['content'])->toBe('<h2>Aangepast</h2><p>Nieuw body</p>');
    expect($sug->fresh()->applied_block_index)->toBe(0);
});

it('falls back to append when previously-applied block was removed', function () {
    [$audit, $subject] = makeReapplyAudit();

    $sug = SeoAuditBlockSuggestion::create([
        'audit_id' => $audit->id,
        'block_index' => null,
        'block_key' => 'outline.0',
        'block_type' => 'content',
        'field_key' => '_new',
        'is_new_block' => true,
        'suggested_value' => '<h2>Eerste</h2>',
        'status' => 'pending',
        'priority' => 'medium',
    ]);

    app(SeoAuditApplier::class)->applySelected($audit, ['blocks' => [$sug->id]], userId: 1);
    expect($sug->fresh()->applied_block_index)->toBe(0);

    $subject->refresh();
    $cb = $subject->customBlocks;
    $cb->setTranslation('blocks', 'nl', []);
    $cb->save();

    app(SeoAuditApplier::class)->applySelected($audit, ['blocks' => [$sug->id]], userId: 1);

    $subject->refresh();
    $blocks = $subject->customBlocks->getTranslation('blocks', 'nl');
    expect($blocks)->toHaveCount(1);
    expect($blocks[0]['data']['content'])->toBe('<h2>Eerste</h2>');
    expect($sug->fresh()->applied_block_index)->toBe(0);
});

it('falls back to append when block type no longer matches at applied_block_index', function () {
    [$audit, $subject] = makeReapplyAudit();

    $sug = SeoAuditBlockSuggestion::create([
        'audit_id' => $audit->id,
        'block_index' => null,
        'block_key' => 'outline.0',
        'block_type' => 'content',
        'field_key' => '_new',
        'is_new_block' => true,
        'suggested_value' => '<h2>Eerste</h2>',
        'status' => 'pending',
        'priority' => 'medium',
    ]);

    app(SeoAuditApplier::class)->applySelected($audit, ['blocks' => [$sug->id]], userId: 1);

    $subject->refresh();
    $cb = $subject->customBlocks;
    $cb->setTranslation('blocks', 'nl', [
        ['type' => 'image', 'data' => ['src' => '/foo.jpg']],
    ]);
    $cb->save();

    app(SeoAuditApplier::class)->applySelected($audit, ['blocks' => [$sug->id]], userId: 1);

    $subject->refresh();
    $blocks = $subject->customBlocks->getTranslation('blocks', 'nl');
    expect($blocks)->toHaveCount(2);
    expect($blocks[0]['type'])->toBe('image');
    expect($blocks[1]['type'])->toBe('content');
    expect($sug->fresh()->applied_block_index)->toBe(1);
});

it('re-applies existing-block field suggestion idempotently without duplicating blocks', function () {
    [$audit, $subject] = makeReapplyAudit();

    $cb = new CustomBlock();
    $cb->blockable_type = FakeReapplySubject::class;
    $cb->blockable_id = $subject->id;
    $cb->setTranslation('blocks', 'nl', [
        ['type' => 'content', 'data' => ['content' => '<p>Oud</p>', 'in_container' => true]],
    ]);
    $cb->save();

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

    app(SeoAuditApplier::class)->applySelected($audit, ['blocks' => [$sug->id]], userId: 1);
    $subject->refresh();
    $blocks = $subject->customBlocks->getTranslation('blocks', 'nl');
    expect($blocks)->toHaveCount(1);
    expect($blocks[0]['data']['content'])->toBe('<p>Nieuw</p>');

    $sug->update(['suggested_value' => '<p>Nog nieuwer</p>', 'status' => 'applied']);
    app(SeoAuditApplier::class)->applySelected($audit, ['blocks' => [$sug->id]], userId: 1);

    $subject->refresh();
    $blocks = $subject->customBlocks->getTranslation('blocks', 'nl');
    expect($blocks)->toHaveCount(1);
    expect($blocks[0]['data']['content'])->toBe('<p>Nog nieuwer</p>');
});

it('re-applies meta suggestion idempotently with new log entry', function () {
    [$audit, $subject] = makeReapplyAudit();

    $sug = SeoAuditMetaSuggestion::create([
        'audit_id' => $audit->id,
        'field' => 'name',
        'suggested_value' => 'Eerste naam',
        'status' => 'pending',
        'priority' => 'high',
    ]);

    app(SeoAuditApplier::class)->applySelected($audit, ['meta' => [$sug->id]], userId: 1);

    $subject->refresh();
    expect($subject->getTranslation('name', 'nl'))->toBe('Eerste naam');
    expect($sug->fresh()->status)->toBe('applied');
    expect(ContentApplyLog::where('audit_id', $audit->id)->count())->toBe(1);

    $sug->update(['suggested_value' => 'Tweede naam', 'status' => 'applied']);
    app(SeoAuditApplier::class)->applySelected($audit, ['meta' => [$sug->id]], userId: 1);

    $subject->refresh();
    expect($subject->getTranslation('name', 'nl'))->toBe('Tweede naam');
    expect(ContentApplyLog::where('audit_id', $audit->id)->count())->toBe(2);
});

it('still skips rejected status on apply', function () {
    [$audit, $subject] = makeReapplyAudit();

    $sug = SeoAuditBlockSuggestion::create([
        'audit_id' => $audit->id,
        'block_index' => null,
        'block_key' => 'outline.0',
        'block_type' => 'content',
        'field_key' => '_new',
        'is_new_block' => true,
        'suggested_value' => '<h2>Rejected</h2>',
        'status' => 'rejected',
        'priority' => 'medium',
    ]);

    $result = app(SeoAuditApplier::class)->applySelected($audit, ['blocks' => [$sug->id]], userId: 1);

    expect($result->applied)->toBe(0);
    expect($result->skipped)->toBe(1);

    $customBlocks = $subject->customBlocks;
    if ($customBlocks) {
        $blocks = $customBlocks->getTranslation('blocks', 'nl') ?? [];
        expect($blocks)->toBeArray()->and($blocks)->toBeEmpty();
    }
});

class FakeLegacyContentSubject extends Model
{
    use HasCustomBlocks;
    use HasTranslations;

    protected $table = 'fake_legacy_content_subjects';
    protected $guarded = [];
    public $timestamps = true;
    public $translatable = ['name', 'content'];
    protected $casts = ['name' => 'array', 'content' => 'array'];
}

it('also mirrors applied blocks to the subjects translatable content column', function () {
    Schema::dropIfExists('fake_legacy_content_subjects');
    Schema::create('fake_legacy_content_subjects', function (Blueprint $table) {
        $table->id();
        $table->json('name')->nullable();
        $table->json('content')->nullable();
        $table->timestamps();
    });

    $subject = FakeLegacyContentSubject::create([]);
    $audit = SeoAudit::create([
        'subject_type' => FakeLegacyContentSubject::class,
        'subject_id' => $subject->id,
        'status' => 'ready',
        'locale' => 'nl',
    ]);

    $sug = SeoAuditBlockSuggestion::create([
        'audit_id' => $audit->id,
        'block_index' => null,
        'block_key' => 'outline.0',
        'block_type' => 'content',
        'field_key' => '_new',
        'is_new_block' => true,
        'suggested_value' => '<h2>Outline</h2><p>Body</p>',
        'status' => 'pending',
        'priority' => 'medium',
    ]);

    app(SeoAuditApplier::class)->applySelected($audit, ['blocks' => [$sug->id]], userId: 1);

    $subject->refresh();

    $customBlocksArr = $subject->customBlocks->getTranslation('blocks', 'nl');
    $contentArr = $subject->getTranslation('content', 'nl');

    expect($customBlocksArr)->toHaveCount(1);
    expect($contentArr)->toHaveCount(1);
    expect($contentArr[0]['type'])->toBe('content');
    expect($contentArr[0]['data']['content'])->toBe('<h2>Outline</h2><p>Body</p>');

    $sug->update(['suggested_value' => '<h2>Overschreven</h2>', 'status' => 'applied']);
    app(SeoAuditApplier::class)->applySelected($audit, ['blocks' => [$sug->id]], userId: 1);

    $subject->refresh();
    $contentArr = $subject->getTranslation('content', 'nl');

    expect($contentArr)->toHaveCount(1);
    expect($contentArr[0]['data']['content'])->toBe('<h2>Overschreven</h2>');
});

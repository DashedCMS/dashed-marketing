<?php

use Dashed\DashedCore\Models\Concerns\HasCustomBlocks;
use Dashed\DashedCore\Models\CustomBlock;
use Dashed\DashedMarketing\Models\SeoAudit;
use Dashed\DashedMarketing\Models\SeoAuditFaqSuggestion;
use Dashed\DashedMarketing\Services\SeoAuditApplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Translatable\HasTranslations;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

class FakeFaqApplySubject extends Model
{
    use HasCustomBlocks;
    use HasTranslations;

    protected $table = 'fake_faq_apply_subjects';
    protected $guarded = [];
    public $timestamps = true;
    public $translatable = ['name'];
    protected $casts = ['name' => 'array'];
}

beforeEach(function () {
    Schema::dropIfExists('fake_faq_apply_subjects');
    Schema::create('fake_faq_apply_subjects', function (Blueprint $table) {
        $table->id();
        $table->json('name')->nullable();
        $table->timestamps();
    });
});

it('creates a new FAQ block when the page has none', function () {
    $subject = FakeFaqApplySubject::create([]);
    $audit = SeoAudit::create([
        'subject_type' => FakeFaqApplySubject::class,
        'subject_id' => $subject->id,
        'status' => 'ready',
        'locale' => 'nl',
    ]);
    $f1 = SeoAuditFaqSuggestion::create(['audit_id' => $audit->id, 'sort_order' => 0, 'question' => 'Q1?', 'answer' => 'A1.', 'priority' => 'medium']);
    $f2 = SeoAuditFaqSuggestion::create(['audit_id' => $audit->id, 'sort_order' => 1, 'question' => 'Q2?', 'answer' => 'A2.', 'priority' => 'medium']);

    $res = app(SeoAuditApplier::class)->applySelected($audit, ['faqs' => [$f1->id, $f2->id], 'faq_target' => 'new'], userId: 1);

    expect($res->applied)->toBe(2);

    $subject->refresh();
    $blocks = $subject->customBlocks->getTranslation('blocks', 'nl');
    expect(end($blocks)['type'])->toBe('faq');
    expect(end($blocks)['data']['questions'])->toHaveCount(2);
    expect(end($blocks)['data']['faqs'])->toHaveCount(2);
});

it('wipes pre-existing FAQ block and creates a fresh one on apply', function () {
    $subject = FakeFaqApplySubject::create([]);

    $cb = new CustomBlock();
    $cb->blockable_type = FakeFaqApplySubject::class;
    $cb->blockable_id = $subject->id;
    $cb->setTranslation('blocks', 'nl', [
        ['type' => 'faq', 'data' => ['title' => 'FAQ', 'questions' => [['question' => 'Bestaand?', 'description' => 'Ja.']], 'faqs' => [['question' => 'Bestaand?', 'description' => 'Ja.']]]],
    ]);
    $cb->save();

    $audit = SeoAudit::create([
        'subject_type' => FakeFaqApplySubject::class,
        'subject_id' => $subject->id,
        'status' => 'ready',
        'locale' => 'nl',
    ]);
    $f1 = SeoAuditFaqSuggestion::create(['audit_id' => $audit->id, 'sort_order' => 0, 'question' => 'Nieuw?', 'answer' => 'Nieuw.', 'priority' => 'medium']);

    $res = app(SeoAuditApplier::class)->applySelected($audit, ['faqs' => [$f1->id], 'faq_target' => 'existing'], userId: 1);

    expect($res->applied)->toBe(1);

    $subject->refresh();
    $blocks = $subject->customBlocks->getTranslation('blocks', 'nl');
    expect($blocks)->toHaveCount(1);
    expect($blocks[0]['type'])->toBe('faq');
    expect($blocks[0]['data']['questions'])->toHaveCount(1);
    expect($blocks[0]['data']['questions'][0]['question'])->toBe('Nieuw?');
});

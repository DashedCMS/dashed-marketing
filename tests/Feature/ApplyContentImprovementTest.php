<?php

use Dashed\DashedMarketing\Jobs\ApplyContentImprovementJob;
use Dashed\DashedMarketing\Models\ContentApplyLog;
use Dashed\DashedMarketing\Models\SeoImprovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

class TestProduct extends Model
{
    protected $table = 'test_products';

    protected $guarded = [];

    public $timestamps = true;
}

beforeEach(function () {
    Schema::dropIfExists('test_products');
    Schema::dropIfExists('dashed__content_apply_logs');
    Schema::dropIfExists('dashed__seo_improvements');

    Schema::create('test_products', function (Blueprint $table) {
        $table->id();
        $table->string('short_description')->nullable();
        $table->timestamps();
    });

    Schema::create('dashed__seo_improvements', function (Blueprint $table) {
        $table->id();
        $table->string('subject_type')->nullable();
        $table->unsignedBigInteger('subject_id')->nullable();
        $table->string('status')->default('analyzing');
        $table->json('keyword_research')->nullable();
        $table->text('analysis_summary')->nullable();
        $table->json('field_proposals')->nullable();
        $table->json('block_proposals')->nullable();
        $table->json('block_proposals_status')->nullable();
        $table->text('error_message')->nullable();
        $table->text('progress_message')->nullable();
        $table->unsignedBigInteger('created_by')->nullable();
        $table->unsignedBigInteger('applied_by')->nullable();
        $table->timestamp('applied_at')->nullable();
        $table->timestamps();
    });

    Schema::create('dashed__content_apply_logs', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('seo_improvement_id')->nullable();
        $table->unsignedBigInteger('content_draft_id')->nullable();
        $table->string('subject_type')->nullable();
        $table->unsignedBigInteger('subject_id')->nullable();
        $table->string('field_key');
        $table->longText('previous_value')->nullable();
        $table->longText('new_value')->nullable();
        $table->unsignedBigInteger('applied_by')->nullable();
        $table->timestamp('applied_at')->nullable();
        $table->timestamp('reverted_at')->nullable();
    });
});

it('applies a field proposal and writes a revert log', function () {
    $product = TestProduct::create(['short_description' => 'Oude tekst']);

    $improvement = SeoImprovement::create([
        'subject_type' => TestProduct::class,
        'subject_id' => $product->id,
        'status' => 'ready',
        'field_proposals' => ['short_description' => 'Nieuwe tekst'],
    ]);

    (new ApplyContentImprovementJob($improvement->id, 'short_description', null, null))->handle();

    expect($product->fresh()->short_description)->toBe('Nieuwe tekst');
    expect(ContentApplyLog::where('seo_improvement_id', $improvement->id)->count())->toBe(1);
    expect($improvement->fresh()->proposalStatus('short_description'))->toBe('applied');

    $log = ContentApplyLog::where('seo_improvement_id', $improvement->id)->first();
    expect(json_decode($log->previous_value, true))->toBe('Oude tekst');
    expect(json_decode($log->new_value, true))->toBe('Nieuwe tekst');
});

it('uses the edited value when supplied', function () {
    $product = TestProduct::create(['short_description' => 'Oude tekst']);

    $improvement = SeoImprovement::create([
        'subject_type' => TestProduct::class,
        'subject_id' => $product->id,
        'status' => 'ready',
        'field_proposals' => ['short_description' => 'Voorgestelde tekst'],
    ]);

    (new ApplyContentImprovementJob($improvement->id, 'short_description', 'Handmatig bewerkt', null))->handle();

    expect($product->fresh()->short_description)->toBe('Handmatig bewerkt');
});

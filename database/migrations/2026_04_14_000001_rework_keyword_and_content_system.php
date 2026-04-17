<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('dashed__keywords', function (Blueprint $table) {
            $table->unsignedInteger('volume_exact')->nullable()->after('volume_indication');
            $table->decimal('cpc', 8, 2)->nullable()->after('volume_exact');
            $table->string('source')->default('manual')->after('cpc');
            $table->timestamp('enriched_at')->nullable()->after('source');
            $table->string('matched_subject_type')->nullable()->after('enriched_at');
            $table->unsignedBigInteger('matched_subject_id')->nullable()->after('matched_subject_type');
            $table->decimal('match_score', 4, 3)->nullable()->after('matched_subject_id');
            $table->string('match_strategy')->nullable()->after('match_score');
            $table->index(['matched_subject_type', 'matched_subject_id'], 'dashed_keywords_match_subject_idx');
        });

        Schema::table('dashed__content_drafts', function (Blueprint $table) {
            $table->json('h2_sections')->nullable()->after('article_content');
            $table->json('history')->nullable()->after('h2_sections');
        });

        Schema::table('dashed__seo_improvements', function (Blueprint $table) {
            $table->json('block_proposals_status')->nullable()->after('block_proposals');
        });

        Schema::create('dashed__keyword_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('keyword_research_id')
                ->constrained('dashed__keyword_researches')
                ->cascadeOnDelete();
            $table->string('filename');
            $table->json('column_mapping');
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedBigInteger('imported_by')->nullable();
            $table->timestamps();
        });

        Schema::create('dashed__content_embeddings', function (Blueprint $table) {
            $table->id();
            $table->string('embeddable_type');
            $table->unsignedBigInteger('embeddable_id');
            $table->longText('vector');
            $table->string('content_hash', 64);
            $table->timestamps();
            $table->unique(['embeddable_type', 'embeddable_id'], 'dashed_content_embeddings_unique');
        });

        Schema::create('dashed__content_apply_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_improvement_id')->nullable()->constrained('dashed__seo_improvements')->nullOnDelete();
            $table->foreignId('content_draft_id')->nullable()->constrained('dashed__content_drafts')->nullOnDelete();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->string('field_key');
            $table->longText('previous_value')->nullable();
            $table->longText('new_value')->nullable();
            $table->unsignedBigInteger('applied_by')->nullable();
            $table->timestamp('applied_at');
            $table->timestamp('reverted_at')->nullable();
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__content_apply_logs');
        Schema::dropIfExists('dashed__content_embeddings');
        Schema::dropIfExists('dashed__keyword_imports');

        Schema::table('dashed__seo_improvements', function (Blueprint $table) {
            $table->dropColumn('block_proposals_status');
        });

        Schema::table('dashed__content_drafts', function (Blueprint $table) {
            $table->dropColumn(['h2_sections', 'history']);
        });

        Schema::table('dashed__keywords', function (Blueprint $table) {
            $table->dropIndex('dashed_keywords_match_subject_idx');
            $table->dropColumn([
                'volume_exact', 'cpc', 'source', 'enriched_at',
                'matched_subject_type', 'matched_subject_id', 'match_score', 'match_strategy',
            ]);
        });
    }
};

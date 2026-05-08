<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('dashed__keyword_researches')) {
            Schema::create('dashed__keyword_researches', function (Blueprint $table) {
                $table->id();
                $table->string('seed_keyword');
                $table->string('locale', 10)->default('nl');
                $table->string('status')->default('pending');
                $table->text('progress_message')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('dashed__keywords')) {
            Schema::create('dashed__keywords', function (Blueprint $table) {
                $table->id();
                $table->foreignId('keyword_research_id')->constrained('dashed__keyword_researches')->cascadeOnDelete();
                $table->string('keyword');
                $table->string('type')->default('secondary');
                $table->string('search_intent')->default('informational');
                $table->string('difficulty')->default('medium');
                $table->string('volume_indication')->default('medium');
                $table->string('status')->default('new');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('dashed__content_clusters')) {
            Schema::create('dashed__content_clusters', function (Blueprint $table) {
                $table->id();
                $table->foreignId('keyword_research_id')->nullable()->constrained('dashed__keyword_researches')->nullOnDelete();
                $table->string('name');
                $table->string('theme')->nullable();
                $table->string('content_type')->default('blog');
                $table->text('description')->nullable();
                $table->string('status')->default('planned');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('dashed__content_cluster_keyword')) {
            Schema::create('dashed__content_cluster_keyword', function (Blueprint $table) {
                $table->foreignId('content_cluster_id')->constrained('dashed__content_clusters')->cascadeOnDelete();
                $table->foreignId('keyword_id')->constrained('dashed__keywords')->cascadeOnDelete();
                $table->primary(['content_cluster_id', 'keyword_id']);
            });
        }

        if (! Schema::hasTable('dashed__content_drafts')) {
            Schema::create('dashed__content_drafts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('content_cluster_id')->nullable()->constrained('dashed__content_clusters')->nullOnDelete();
                $table->string('keyword');
                $table->string('locale', 10)->default('nl');
                $table->text('instruction')->nullable();
                $table->string('status')->default('pending');
                $table->text('progress_message')->nullable();
                $table->text('error_message')->nullable();
                $table->json('content_plan')->nullable();
                $table->json('article_content')->nullable();
                $table->nullableMorphs('subject');
                $table->unsignedBigInteger('applied_by')->nullable();
                $table->timestamp('applied_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('dashed__seo_improvements')) {
            Schema::create('dashed__seo_improvements', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('subject_type');
                $table->unsignedBigInteger('subject_id');
                $table->enum('status', ['analyzing', 'ready', 'applied', 'failed'])->default('analyzing');
                $table->json('keyword_research')->nullable();
                $table->text('analysis_summary')->nullable();
                $table->json('field_proposals')->nullable();
                $table->json('block_proposals')->nullable();
                $table->text('error_message')->nullable();
                $table->text('progress_message')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('applied_by')->nullable();
                $table->timestamp('applied_at')->nullable();
                $table->timestamps();
                $table->unique(['subject_type', 'subject_id'], 'seo_improvement_subject_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__content_cluster_keyword');
        Schema::dropIfExists('dashed__content_drafts');
        Schema::dropIfExists('dashed__content_clusters');
        Schema::dropIfExists('dashed__keywords');
        Schema::dropIfExists('dashed__keyword_researches');
        Schema::dropIfExists('dashed__seo_improvements');
    }
};

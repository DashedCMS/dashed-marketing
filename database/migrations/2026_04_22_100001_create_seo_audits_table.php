<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashed__seo_audits', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->string('status')->default('analyzing');
            $table->unsignedTinyInteger('overall_score')->nullable();
            $table->json('score_breakdown')->nullable();
            $table->text('analysis_summary')->nullable();
            $table->text('progress_message')->nullable();
            $table->text('error_message')->nullable();
            $table->text('instruction')->nullable();
            $table->string('locale', 8)->default('nl');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('applied_by')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id'], 'seo_audits_subject_idx');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__seo_audits');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashed__seo_audit_internal_link_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained('dashed__seo_audits')->cascadeOnDelete();
            $table->string('anchor_text', 500);
            $table->string('target_url', 2048);
            $table->string('target_subject_type')->nullable();
            $table->unsignedBigInteger('target_subject_id')->nullable();
            $table->text('context_description');
            $table->text('reason')->nullable();
            $table->string('priority')->default('medium');
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->index(['target_subject_type', 'target_subject_id'], 'audit_internal_link_target_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__seo_audit_internal_link_suggestions');
    }
};

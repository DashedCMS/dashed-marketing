<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashed__seo_audit_page_analysis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->unique()->constrained('dashed__seo_audits')->cascadeOnDelete();
            $table->json('headings_structure')->nullable();
            $table->unsignedInteger('content_length')->nullable();
            $table->json('keyword_density')->nullable();
            $table->json('alt_text_coverage')->nullable();
            $table->unsignedTinyInteger('readability_score')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__seo_audit_page_analysis');
    }
};

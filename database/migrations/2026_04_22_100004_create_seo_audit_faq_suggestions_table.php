<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashed__seo_audit_faq_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained('dashed__seo_audits')->cascadeOnDelete();
            $table->integer('sort_order')->default(0);
            $table->string('question', 500);
            $table->text('answer');
            $table->string('target_keyword')->nullable();
            $table->string('priority')->default('medium');
            $table->string('status')->default('pending');
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index('audit_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__seo_audit_faq_suggestions');
    }
};

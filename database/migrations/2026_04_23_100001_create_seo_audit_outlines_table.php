<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashed__seo_audit_outlines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->unique()->constrained('dashed__seo_audits')->cascadeOnDelete();
            $table->string('h1', 500)->nullable();
            $table->text('summary')->nullable();
            $table->json('headings')->nullable();
            $table->timestamp('content_generated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__seo_audit_outlines');
    }
};

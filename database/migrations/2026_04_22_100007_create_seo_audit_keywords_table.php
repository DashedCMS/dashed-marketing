<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashed__seo_audit_keywords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained('dashed__seo_audits')->cascadeOnDelete();
            $table->string('keyword');
            $table->string('type');
            $table->string('intent')->nullable();
            $table->string('volume_indication')->nullable();
            $table->string('priority')->default('medium');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['audit_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__seo_audit_keywords');
    }
};

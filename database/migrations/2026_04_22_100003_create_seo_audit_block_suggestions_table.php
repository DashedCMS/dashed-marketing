<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashed__seo_audit_block_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained('dashed__seo_audits')->cascadeOnDelete();
            $table->unsignedSmallInteger('block_index')->nullable();
            $table->string('block_key')->nullable();
            $table->string('block_type');
            $table->string('field_key');
            $table->boolean('is_new_block')->default(false);
            $table->text('current_value')->nullable();
            $table->longText('suggested_value');
            $table->text('reason')->nullable();
            $table->string('priority')->default('medium');
            $table->string('status')->default('pending');
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['audit_id', 'block_index'], 'audit_block_suggestion_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__seo_audit_block_suggestions');
    }
};

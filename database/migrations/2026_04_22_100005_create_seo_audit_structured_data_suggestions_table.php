<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashed__seo_audit_structured_data_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained('dashed__seo_audits')->cascadeOnDelete();
            $table->string('schema_type');
            $table->longText('json_ld');
            $table->text('reason')->nullable();
            $table->string('priority')->default('medium');
            $table->string('status')->default('pending');
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->unique(['audit_id', 'schema_type'], 'audit_structured_data_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__seo_audit_structured_data_suggestions');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dashed__content_draft_keyword')) {
            Schema::create('dashed__content_draft_keyword', function (Blueprint $table) {
                $table->foreignId('content_draft_id')
                    ->constrained('dashed__content_drafts')
                    ->cascadeOnDelete();
                $table->foreignId('keyword_id')
                    ->constrained('dashed__keywords')
                    ->cascadeOnDelete();
                $table->primary(['content_draft_id', 'keyword_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__content_draft_keyword');
    }
};

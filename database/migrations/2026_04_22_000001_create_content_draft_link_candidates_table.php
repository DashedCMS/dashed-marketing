<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dashed__content_draft_link_candidates')) {
            return;
        }

        Schema::create('dashed__content_draft_link_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_draft_id')
                ->constrained('dashed__content_drafts')
                ->cascadeOnDelete();
            $table->integer('sort_order')->default(0);
            $table->string('type')->nullable();
            $table->string('title');
            $table->string('url', 2048);
            $table->timestamps();

            $table->index(['content_draft_id', 'sort_order'], 'dashed__cd_link_candidates_draft_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__content_draft_link_candidates');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dashed__article_drafts') && ! Schema::hasTable('dashed__content_drafts')) {
            Schema::rename('dashed__article_drafts', 'dashed__content_drafts');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('dashed__content_drafts') && ! Schema::hasTable('dashed__article_drafts')) {
            Schema::rename('dashed__content_drafts', 'dashed__article_drafts');
        }
    }
};

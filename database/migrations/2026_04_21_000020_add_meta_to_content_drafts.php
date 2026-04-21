<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dashed__content_drafts', function (Blueprint $table) {
            if (! Schema::hasColumn('dashed__content_drafts', 'meta_title')) {
                $table->string('meta_title', 150)->nullable()->after('slug');
            }
            if (! Schema::hasColumn('dashed__content_drafts', 'meta_description')) {
                $table->string('meta_description', 250)->nullable()->after('meta_title');
            }
        });
    }

    public function down(): void
    {
        Schema::table('dashed__content_drafts', function (Blueprint $table) {
            $table->dropColumn(['meta_title', 'meta_description']);
        });
    }
};

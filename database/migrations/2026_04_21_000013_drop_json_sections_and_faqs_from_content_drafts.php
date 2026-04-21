<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dashed__content_drafts', function (Blueprint $table) {
            if (Schema::hasColumn('dashed__content_drafts', 'h2_sections')) {
                $table->dropColumn('h2_sections');
            }
            if (Schema::hasColumn('dashed__content_drafts', 'faqs')) {
                $table->dropColumn('faqs');
            }
        });
    }

    public function down(): void
    {
        Schema::table('dashed__content_drafts', function (Blueprint $table) {
            $table->json('h2_sections')->nullable()->after('applied_at');
            $table->json('faqs')->nullable()->after('h2_sections');
        });
    }
};

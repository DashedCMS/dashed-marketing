<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dashed__content_drafts', function (Blueprint $table) {
            $table->json('faqs')->nullable()->after('h2_sections');
        });
    }

    public function down(): void
    {
        Schema::table('dashed__content_drafts', function (Blueprint $table) {
            $table->dropColumn('faqs');
        });
    }
};

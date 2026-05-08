<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
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

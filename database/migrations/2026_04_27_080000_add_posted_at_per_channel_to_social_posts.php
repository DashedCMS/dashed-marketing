<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dashed__social_posts', function (Blueprint $table) {
            $table->json('posted_at_per_channel')->nullable()->after('posted_at');
        });
    }

    public function down(): void
    {
        Schema::table('dashed__social_posts', function (Blueprint $table) {
            $table->dropColumn('posted_at_per_channel');
        });
    }
};

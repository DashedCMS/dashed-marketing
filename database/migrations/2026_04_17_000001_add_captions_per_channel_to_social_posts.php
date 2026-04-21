<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dashed__social_posts', function (Blueprint $table) {
            $table->boolean('captions_per_channel')->default(false)->after('channel_captions');
        });

        DB::table('dashed__social_posts')
            ->whereNotNull('channel_captions')
            ->where('channel_captions', '!=', '[]')
            ->where('channel_captions', '!=', '{}')
            ->where('channel_captions', '!=', '')
            ->update(['captions_per_channel' => true]);
    }

    public function down(): void
    {
        Schema::table('dashed__social_posts', function (Blueprint $table) {
            $table->dropColumn('captions_per_channel');
        });
    }
};

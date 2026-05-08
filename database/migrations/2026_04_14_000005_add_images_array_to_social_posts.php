<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('dashed__social_posts')) {
            return;
        }

        Schema::table('dashed__social_posts', function (Blueprint $t) {
            if (! Schema::hasColumn('dashed__social_posts', 'images')) {
                $t->json('images')->nullable()->after('image_path');
            }
        });

        // Backfill: copy any existing image_path into the new images array.
        DB::table('dashed__social_posts')
            ->whereNotNull('image_path')
            ->whereNull('images')
            ->orderBy('id')
            ->each(function ($row) {
                DB::table('dashed__social_posts')
                    ->where('id', $row->id)
                    ->update(['images' => json_encode([$row->image_path])]);
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('dashed__social_posts')) {
            return;
        }

        Schema::table('dashed__social_posts', function (Blueprint $t) {
            if (Schema::hasColumn('dashed__social_posts', 'images')) {
                $t->dropColumn('images');
            }
        });
    }
};

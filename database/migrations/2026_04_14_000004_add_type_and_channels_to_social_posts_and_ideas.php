<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    private const PLATFORM_TO_TYPE = [
        'instagram_feed' => 'post',
        'instagram_reels' => 'reel',
        'instagram_story' => 'story',
        'pinterest' => 'post',
        'facebook' => 'post',
        'facebook_page' => 'post',
        'tiktok' => 'reel',
    ];

    public function up(): void
    {
        foreach (['dashed__social_posts', 'dashed__social_ideas'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table) {
                if (! Schema::hasColumn($table, 'type')) {
                    $t->string('type')->nullable()->after('platform');
                }
                if (! Schema::hasColumn($table, 'channels')) {
                    $t->json('channels')->nullable()->after('type');
                }
            });

            $rows = DB::table($table)->select(['id', 'platform'])->get();
            foreach ($rows as $row) {
                $platform = $row->platform ?: null;
                $type = $platform ? (self::PLATFORM_TO_TYPE[$platform] ?? 'post') : null;
                $channels = $platform ? json_encode([$platform]) : null;

                DB::table($table)
                    ->where('id', $row->id)
                    ->update([
                        'type' => $type,
                        'channels' => $channels,
                    ]);
            }
        }
    }

    public function down(): void
    {
        foreach (['dashed__social_posts', 'dashed__social_ideas'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table) {
                if (Schema::hasColumn($table, 'channels')) {
                    $t->dropColumn('channels');
                }
                if (Schema::hasColumn($table, 'type')) {
                    $t->dropColumn('type');
                }
            });
        }
    }
};

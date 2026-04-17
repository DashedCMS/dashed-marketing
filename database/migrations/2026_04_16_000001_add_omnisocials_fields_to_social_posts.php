<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('dashed__social_posts', function (Blueprint $table) {
            $table->json('channel_captions')->nullable()->after('caption');
            $table->json('ratio_images')->nullable()->after('images');
            $table->string('external_id')->nullable()->after('post_url');
            $table->json('external_data')->nullable()->after('external_id');
            $table->json('failed_platforms')->nullable()->after('external_data');
            $table->unsignedSmallInteger('retry_count')->default(0)->after('failed_platforms');
            $table->timestamp('analytics_synced_at')->nullable()->after('retry_count');

            $table->index('external_id');
        });
    }

    public function down(): void
    {
        Schema::table('dashed__social_posts', function (Blueprint $table) {
            $table->dropIndex(['external_id']);
            $table->dropColumn([
                'channel_captions',
                'ratio_images',
                'external_id',
                'external_data',
                'failed_platforms',
                'retry_count',
                'analytics_synced_at',
            ]);
        });
    }
};

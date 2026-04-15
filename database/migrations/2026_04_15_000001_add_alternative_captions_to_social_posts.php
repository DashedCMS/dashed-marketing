<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dashed__social_posts')) {
            return;
        }

        Schema::table('dashed__social_posts', function (Blueprint $t) {
            if (! Schema::hasColumn('dashed__social_posts', 'alternative_captions')) {
                $t->json('alternative_captions')->nullable()->after('caption');
            }
            if (! Schema::hasColumn('dashed__social_posts', 'alternative_image_prompts')) {
                $t->json('alternative_image_prompts')->nullable()->after('image_prompt');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('dashed__social_posts')) {
            return;
        }

        Schema::table('dashed__social_posts', function (Blueprint $t) {
            if (Schema::hasColumn('dashed__social_posts', 'alternative_captions')) {
                $t->dropColumn('alternative_captions');
            }
            if (Schema::hasColumn('dashed__social_posts', 'alternative_image_prompts')) {
                $t->dropColumn('alternative_image_prompts');
            }
        });
    }
};

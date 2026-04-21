<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('dashed__content_clusters', function (Blueprint $table) {
            if (! Schema::hasColumn('dashed__content_clusters', 'pending_concepts')) {
                $table->json('pending_concepts')->nullable()->after('description');
            }
        });

        Schema::table('dashed__content_drafts', function (Blueprint $table) {
            if (! Schema::hasColumn('dashed__content_drafts', 'name')) {
                $table->string('name')->nullable()->after('content_cluster_id');
            }
            if (! Schema::hasColumn('dashed__content_drafts', 'slug')) {
                $table->string('slug')->nullable()->after('name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('dashed__content_clusters', function (Blueprint $table) {
            $table->dropColumn('pending_concepts');
        });

        Schema::table('dashed__content_drafts', function (Blueprint $table) {
            $table->dropColumn(['name', 'slug']);
        });
    }
};
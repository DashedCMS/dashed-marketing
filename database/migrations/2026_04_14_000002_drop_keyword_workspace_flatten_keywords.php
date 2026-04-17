<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('dashed__keywords', function (Blueprint $table) {
            $table->string('locale', 8)->default('nl')->after('keyword');
            $table->index('locale');
        });

        Schema::table('dashed__content_clusters', function (Blueprint $table) {
            $table->string('locale', 8)->default('nl')->after('name');
            $table->index('locale');
        });

        // Backfill locale from the about-to-be-dropped workspace
        if (Schema::hasTable('dashed__keyword_researches')) {
            DB::statement(<<<'SQL'
                UPDATE dashed__keywords
                SET locale = COALESCE(
                    (SELECT locale FROM dashed__keyword_researches WHERE dashed__keyword_researches.id = dashed__keywords.keyword_research_id),
                    'nl'
                )
            SQL);

            DB::statement(<<<'SQL'
                UPDATE dashed__content_clusters
                SET locale = COALESCE(
                    (SELECT locale FROM dashed__keyword_researches WHERE dashed__keyword_researches.id = dashed__content_clusters.keyword_research_id),
                    'nl'
                )
            SQL);
        }

        Schema::table('dashed__keywords', function (Blueprint $table) {
            if (Schema::hasColumn('dashed__keywords', 'keyword_research_id')) {
                try {
                    $table->dropForeign(['keyword_research_id']);
                } catch (Throwable) {
                }
                $table->dropColumn('keyword_research_id');
            }
        });

        Schema::table('dashed__content_clusters', function (Blueprint $table) {
            if (Schema::hasColumn('dashed__content_clusters', 'keyword_research_id')) {
                try {
                    $table->dropForeign(['keyword_research_id']);
                } catch (Throwable) {
                }
                $table->dropColumn('keyword_research_id');
            }
        });

        Schema::table('dashed__keyword_imports', function (Blueprint $table) {
            if (Schema::hasColumn('dashed__keyword_imports', 'keyword_research_id')) {
                try {
                    $table->dropForeign(['keyword_research_id']);
                } catch (Throwable) {
                }
                $table->dropColumn('keyword_research_id');
            }
            if (! Schema::hasColumn('dashed__keyword_imports', 'locale')) {
                $table->string('locale', 8)->default('nl')->after('filename');
                $table->index('locale');
            }
        });

        Schema::dropIfExists('dashed__keyword_researches');
    }

    public function down(): void
    {
        // Forward-only. Down is not supported because dropping the workspace
        // concept loses the grouping semantics; re-creating would require
        // manual reconstruction.
    }
};

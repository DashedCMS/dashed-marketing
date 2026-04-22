<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dashed__content_draft_link_candidates')) {
            return;
        }

        Schema::table('dashed__content_draft_link_candidates', function (Blueprint $table) {
            if (! Schema::hasColumn('dashed__content_draft_link_candidates', 'subject_type')) {
                $table->string('subject_type')->nullable()->after('content_draft_id');
            }
            if (! Schema::hasColumn('dashed__content_draft_link_candidates', 'subject_id')) {
                $table->unsignedBigInteger('subject_id')->nullable()->after('subject_type');
                $table->index(['subject_type', 'subject_id'], 'content_draft_link_cand_subject_idx');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('dashed__content_draft_link_candidates')) {
            return;
        }

        Schema::table('dashed__content_draft_link_candidates', function (Blueprint $table) {
            if (Schema::hasColumn('dashed__content_draft_link_candidates', 'subject_id')) {
                $table->dropIndex('content_draft_link_cand_subject_idx');
            }
            $table->dropColumn(['subject_type', 'subject_id']);
        });
    }
};

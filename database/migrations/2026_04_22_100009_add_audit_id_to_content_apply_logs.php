<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dashed__content_apply_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('dashed__content_apply_logs', 'audit_id')) {
                $table->unsignedBigInteger('audit_id')->nullable()->after('seo_improvement_id');
                $table->index('audit_id', 'content_apply_logs_audit_idx');
                $table->foreign('audit_id')
                    ->references('id')->on('dashed__seo_audits')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('dashed__content_apply_logs', function (Blueprint $table) {
            if (Schema::hasColumn('dashed__content_apply_logs', 'audit_id')) {
                $table->dropForeign(['audit_id']);
                $table->dropIndex('content_apply_logs_audit_idx');
                $table->dropColumn('audit_id');
            }
        });
    }
};

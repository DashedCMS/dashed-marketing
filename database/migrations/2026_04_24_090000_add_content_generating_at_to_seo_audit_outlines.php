<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dashed__seo_audit_outlines', function (Blueprint $table) {
            $table->timestamp('content_generating_at')->nullable()->after('content_generated_at');
        });
    }

    public function down(): void
    {
        Schema::table('dashed__seo_audit_outlines', function (Blueprint $table) {
            $table->dropColumn('content_generating_at');
        });
    }
};

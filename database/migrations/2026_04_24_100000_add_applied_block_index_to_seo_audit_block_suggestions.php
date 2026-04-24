<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dashed__seo_audit_block_suggestions', function (Blueprint $table) {
            $table->unsignedInteger('applied_block_index')->nullable()->after('block_index');
        });
    }

    public function down(): void
    {
        Schema::table('dashed__seo_audit_block_suggestions', function (Blueprint $table) {
            $table->dropColumn('applied_block_index');
        });
    }
};

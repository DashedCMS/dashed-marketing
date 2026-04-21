<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashed__social_pillars', function (Blueprint $table) {
            $table->id();
            $table->string('site_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('target_percentage')->default(0);
            $table->string('color', 7)->default('#6B7280');
            $table->timestamps();

            $table->index('site_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__social_pillars');
    }
};

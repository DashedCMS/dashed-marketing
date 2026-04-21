<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashed__social_ideas', function (Blueprint $table) {
            $table->id();
            $table->string('site_id');
            $table->string('title');
            $table->string('platform')->nullable();
            $table->foreignId('pillar_id')->nullable()->constrained('dashed__social_pillars')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->string('status')->default('idea');
            $table->json('tags')->nullable();
            $table->timestamps();

            $table->index('site_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__social_ideas');
    }
};

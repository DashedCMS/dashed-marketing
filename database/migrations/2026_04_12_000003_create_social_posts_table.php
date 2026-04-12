<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('dashed__social_posts', function (Blueprint $table) {
            $table->id();
            $table->string('site_id');
            $table->string('platform');
            $table->string('status')->default('concept');
            $table->text('caption')->nullable();
            $table->string('image_path')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->string('post_url')->nullable();
            $table->foreignId('pillar_id')->nullable()->constrained('dashed__social_pillars')->nullOnDelete();
            $table->nullableMorphs('subject');
            $table->foreignId('campaign_id')->nullable()->constrained('dashed__social_campaigns')->nullOnDelete();
            $table->json('performance_data')->nullable();
            $table->json('hashtags')->nullable();
            $table->text('alt_text')->nullable();
            $table->text('image_prompt')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'status']);
            $table->index(['site_id', 'platform']);
            $table->index('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__social_posts');
    }
};

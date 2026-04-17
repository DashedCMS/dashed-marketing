<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('dashed__social_post_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('dashed__social_posts')->cascadeOnDelete();
            $table->text('caption')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__social_post_versions');
    }
};

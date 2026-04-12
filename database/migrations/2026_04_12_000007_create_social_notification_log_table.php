<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('dashed__social_notification_log', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('site_id');
            $table->timestamp('sent_at');
            $table->string('recipient');
            $table->text('content')->nullable();

            $table->index(['site_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__social_notification_log');
    }
};

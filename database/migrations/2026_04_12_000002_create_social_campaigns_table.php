<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashed__social_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('site_id');
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->text('focus')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index('site_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__social_campaigns');
    }
};

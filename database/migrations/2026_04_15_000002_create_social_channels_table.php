<?php

use Dashed\DashedCore\Classes\Sites;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Dashed\DashedMarketing\Database\Seeders\SocialChannelSeeder;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('dashed__social_channels', function (Blueprint $table) {
            $table->id();
            $table->string('site_id');
            $table->string('name');
            $table->string('slug');
            $table->json('accepted_types');
            $table->json('meta')->nullable();
            $table->unsignedSmallInteger('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['site_id', 'slug']);
            $table->index('site_id');
        });

        $this->seedExistingSites();
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__social_channels');
    }

    public function seedExistingSites(): void
    {
        $seeder = new SocialChannelSeeder();

        foreach (Sites::getSites() as $site) {
            $seeder->seedSite((string) $site['id']);
        }
    }
};

<?php

use Dashed\DashedAi\Facades\Ai;
use Dashed\DashedMarketing\Jobs\ClusterKeywordsJob;
use Dashed\DashedMarketing\Models\ContentCluster;
use Dashed\DashedMarketing\Models\Keyword;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Schema::dropIfExists('dashed__content_cluster_keyword');
    Schema::dropIfExists('dashed__content_clusters');
    Schema::dropIfExists('dashed__keywords');

    Schema::create('dashed__keywords', function (Blueprint $table) {
        $table->id();
        $table->string('keyword');
        $table->string('locale', 8)->default('nl');
        $table->string('type')->default('secondary');
        $table->string('search_intent')->default('informational');
        $table->string('difficulty')->default('medium');
        $table->string('volume_indication')->default('medium');
        $table->unsignedInteger('volume_exact')->nullable();
        $table->decimal('cpc', 8, 2)->nullable();
        $table->string('source')->default('manual');
        $table->timestamp('enriched_at')->nullable();
        $table->string('matched_subject_type')->nullable();
        $table->unsignedBigInteger('matched_subject_id')->nullable();
        $table->decimal('match_score', 4, 3)->nullable();
        $table->string('match_strategy')->nullable();
        $table->string('status')->default('new');
        $table->text('notes')->nullable();
        $table->timestamps();
    });

    Schema::create('dashed__content_clusters', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('locale', 8)->default('nl');
        $table->string('theme')->nullable();
        $table->string('content_type')->default('blog');
        $table->text('description')->nullable();
        $table->string('status')->default('planned');
        $table->timestamps();
    });

    Schema::create('dashed__content_cluster_keyword', function (Blueprint $table) {
        $table->unsignedBigInteger('content_cluster_id');
        $table->unsignedBigInteger('keyword_id');
        $table->primary(['content_cluster_id', 'keyword_id']);
    });
});

it('creates clusters from approved keywords only, rejecting hallucinated ones', function () {
    Keyword::create([
        'keyword' => 'waxinelichthouder modern',
        'locale' => 'nl',
        'type' => 'primary',
        'status' => 'new',
        'source' => 'manual',
    ]);
    Keyword::create([
        'keyword' => 'design waxinelichthouder',
        'locale' => 'nl',
        'type' => 'primary',
        'status' => 'new',
        'source' => 'manual',
    ]);

    Ai::shouldReceive('json')->once()->andReturn([
        'clusters' => [
            [
                'name' => 'Waxinelichthouder',
                'theme' => 'Design waxinelichthouders',
                'content_type' => 'category',
                'description' => 'Categorie voor design waxinelichthouders',
                'keywords' => ['waxinelichthouder modern', 'design waxinelichthouder', 'nep-keyword-die-niet-bestaat'],
            ],
        ],
    ]);

    (new ClusterKeywordsJob('nl', 'full'))->handle();

    expect(ContentCluster::count())->toBe(1);
    $cluster = ContentCluster::first();
    expect($cluster->locale)->toBe('nl');
    expect($cluster->keywords()->count())->toBe(2);
});

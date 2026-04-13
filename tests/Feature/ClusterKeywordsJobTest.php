<?php

use Dashed\DashedAi\Facades\Ai;
use Dashed\DashedMarketing\Jobs\ClusterKeywordsJob;
use Dashed\DashedMarketing\Models\ContentCluster;
use Dashed\DashedMarketing\Models\Keyword;
use Dashed\DashedMarketing\Models\KeywordResearch;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

uses(\Tests\TestCase::class);

beforeEach(function () {
    Schema::dropIfExists('dashed__content_cluster_keyword');
    Schema::dropIfExists('dashed__content_clusters');
    Schema::dropIfExists('dashed__keywords');
    Schema::dropIfExists('dashed__keyword_researches');

    Schema::create('dashed__keyword_researches', function (Blueprint $table) {
        $table->id();
        $table->string('seed_keyword');
        $table->string('locale', 10)->default('nl');
        $table->string('status')->default('pending');
        $table->text('progress_message')->nullable();
        $table->text('error_message')->nullable();
        $table->timestamps();
    });

    Schema::create('dashed__keywords', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('keyword_research_id');
        $table->string('keyword');
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
        $table->unsignedBigInteger('keyword_research_id')->nullable();
        $table->string('name');
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
    $workspace = KeywordResearch::create([
        'seed_keyword' => 'waxinelichthouder',
        'locale' => 'nl',
        'status' => 'ready',
    ]);

    Keyword::create([
        'keyword_research_id' => $workspace->id,
        'keyword' => 'waxinelichthouder modern',
        'type' => 'primary',
        'status' => 'new',
        'source' => 'manual',
    ]);
    Keyword::create([
        'keyword_research_id' => $workspace->id,
        'keyword' => 'design waxinelichthouder',
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

    (new ClusterKeywordsJob($workspace->id, 'full'))->handle();

    expect(ContentCluster::count())->toBe(1);
    $cluster = ContentCluster::first();
    expect($cluster->keywords()->count())->toBe(2);
});

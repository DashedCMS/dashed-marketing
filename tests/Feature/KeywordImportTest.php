<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Dashed\DashedMarketing\Models\Keyword;
use Dashed\DashedMarketing\Models\KeywordImport;
use Dashed\DashedMarketing\Jobs\ImportKeywordsJob;
use Dashed\DashedMarketing\Models\KeywordResearch;

uses(\Tests\TestCase::class);

beforeEach(function () {
    // Build only the tables we need so we don't have to run all package
    // migrations (dashed-ecommerce-core has an alter-table that breaks sqlite).
    Schema::dropIfExists('dashed__content_cluster_keyword');
    Schema::dropIfExists('dashed__keywords');
    Schema::dropIfExists('dashed__keyword_imports');
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

    Schema::create('dashed__keyword_imports', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('keyword_research_id');
        $table->string('filename');
        $table->json('column_mapping');
        $table->unsignedInteger('row_count')->default(0);
        $table->unsignedBigInteger('imported_by')->nullable();
        $table->timestamps();
    });
});

it('imports rows from a parsed dataset into a workspace', function () {
    $workspace = KeywordResearch::create([
        'seed_keyword' => 'test',
        'locale' => 'nl',
        'status' => 'ready',
    ]);

    $rows = [
        ['keyword' => 'theelichthouder', 'volume_exact' => 1200, 'search_intent' => 'commercial'],
        ['keyword' => 'waxinelichthouder', 'volume_exact' => 800, 'search_intent' => 'commercial'],
    ];

    $import = KeywordImport::create([
        'keyword_research_id' => $workspace->id,
        'filename' => 'test.csv',
        'column_mapping' => ['0' => 'keyword', '1' => 'volume_exact', '2' => 'search_intent'],
        'row_count' => count($rows),
    ]);

    (new ImportKeywordsJob($workspace->id, $import->id, $rows, 'skip'))->handle();

    expect(Keyword::count())->toBe(2);
    expect(Keyword::where('keyword', 'theelichthouder')->first()->volume_exact)->toBe(1200);
});

it('skips duplicates within the same workspace and locale', function () {
    $workspace = KeywordResearch::create([
        'seed_keyword' => 'test',
        'locale' => 'nl',
        'status' => 'ready',
    ]);

    Keyword::create([
        'keyword_research_id' => $workspace->id,
        'keyword' => 'theelichthouder',
        'type' => 'primary',
        'status' => 'new',
        'source' => 'manual',
    ]);

    $import = KeywordImport::create([
        'keyword_research_id' => $workspace->id,
        'filename' => 'test.csv',
        'column_mapping' => ['0' => 'keyword'],
        'row_count' => 1,
    ]);

    (new ImportKeywordsJob($workspace->id, $import->id, [['keyword' => 'theelichthouder']], 'skip'))->handle();

    expect(Keyword::count())->toBe(1);
});

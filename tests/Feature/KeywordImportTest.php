<?php

use Dashed\DashedMarketing\Jobs\ImportKeywordsJob;
use Dashed\DashedMarketing\Models\Keyword;
use Dashed\DashedMarketing\Models\KeywordImport;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

uses(\Tests\TestCase::class);

beforeEach(function () {
    // Build only the tables we need so we don't have to run all package
    // migrations (dashed-ecommerce-core has an alter-table that breaks sqlite).
    Schema::dropIfExists('dashed__content_cluster_keyword');
    Schema::dropIfExists('dashed__keywords');
    Schema::dropIfExists('dashed__keyword_imports');

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

    Schema::create('dashed__keyword_imports', function (Blueprint $table) {
        $table->id();
        $table->string('filename');
        $table->string('locale', 8)->default('nl');
        $table->json('column_mapping');
        $table->unsignedInteger('row_count')->default(0);
        $table->unsignedBigInteger('imported_by')->nullable();
        $table->timestamps();
    });
});

it('imports rows from a parsed dataset into the given locale', function () {
    $rows = [
        ['keyword' => 'theelichthouder', 'volume_exact' => 1200, 'search_intent' => 'commercial'],
        ['keyword' => 'waxinelichthouder', 'volume_exact' => 800, 'search_intent' => 'commercial'],
    ];

    $import = KeywordImport::create([
        'filename' => 'test.csv',
        'locale' => 'nl',
        'column_mapping' => ['0' => 'keyword', '1' => 'volume_exact', '2' => 'search_intent'],
        'row_count' => count($rows),
    ]);

    (new ImportKeywordsJob('nl', $import->id, $rows, 'skip'))->handle();

    expect(Keyword::count())->toBe(2);
    expect(Keyword::where('keyword', 'theelichthouder')->first()->volume_exact)->toBe(1200);
    expect(Keyword::where('keyword', 'theelichthouder')->first()->locale)->toBe('nl');
});

it('skips duplicates within the same locale', function () {
    Keyword::create([
        'keyword' => 'theelichthouder',
        'locale' => 'nl',
        'type' => 'primary',
        'status' => 'new',
        'source' => 'manual',
    ]);

    $import = KeywordImport::create([
        'filename' => 'test.csv',
        'locale' => 'nl',
        'column_mapping' => ['0' => 'keyword'],
        'row_count' => 1,
    ]);

    (new ImportKeywordsJob('nl', $import->id, [['keyword' => 'theelichthouder']], 'skip'))->handle();

    expect(Keyword::count())->toBe(1);
});

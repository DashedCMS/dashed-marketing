<?php

use Dashed\DashedMarketing\Filament\Resources\KeywordResource\Pages\ListKeywords;
use Dashed\DashedMarketing\Models\Keyword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('approve row action sets status to approved', function () {
    $keyword = Keyword::create(['keyword' => 'test approve', 'locale' => 'nl', 'status' => 'new']);

    Livewire::test(ListKeywords::class)
        ->callTableAction('approve', $keyword);

    expect($keyword->fresh()->status)->toBe('approved');
});

it('reject row action sets status to rejected', function () {
    $keyword = Keyword::create(['keyword' => 'test reject', 'locale' => 'nl', 'status' => 'new']);

    Livewire::test(ListKeywords::class)
        ->callTableAction('reject', $keyword);

    expect($keyword->fresh()->status)->toBe('rejected');
});

it('hides approve action when already approved', function () {
    $keyword = Keyword::create(['keyword' => 'already approved', 'locale' => 'nl', 'status' => 'approved']);

    Livewire::test(ListKeywords::class)
        ->assertTableActionHidden('approve', $keyword)
        ->assertTableActionVisible('reject', $keyword);
});

it('bulk approve and reject update all selected', function () {
    $a = Keyword::create(['keyword' => 'bulk a', 'locale' => 'nl', 'status' => 'new']);
    $b = Keyword::create(['keyword' => 'bulk b', 'locale' => 'nl', 'status' => 'new']);

    Livewire::test(ListKeywords::class)
        ->callTableBulkAction('approve', [$a->id, $b->id]);

    expect($a->fresh()->status)->toBe('approved');
    expect($b->fresh()->status)->toBe('approved');

    Livewire::test(ListKeywords::class)
        ->callTableBulkAction('reject', [$a->id, $b->id]);

    expect($a->fresh()->status)->toBe('rejected');
    expect($b->fresh()->status)->toBe('rejected');
});

it('rejected keywords hidden by default and shown via filter', function () {
    $active = Keyword::create(['keyword' => 'active one', 'locale' => 'nl', 'status' => 'new']);
    $rejected = Keyword::create(['keyword' => 'rejected one', 'locale' => 'nl', 'status' => 'rejected']);

    Livewire::test(ListKeywords::class)
        ->assertCanSeeTableRecords([$active])
        ->assertCanNotSeeTableRecords([$rejected])
        ->filterTable('show_rejected', true)
        ->assertCanSeeTableRecords([$rejected]);
});

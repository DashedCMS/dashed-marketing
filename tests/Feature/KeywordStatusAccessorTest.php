<?php

use Dashed\DashedMarketing\Models\Keyword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('labels rejected status correctly', function () {
    $keyword = new Keyword(['status' => 'rejected']);

    expect($keyword->status_label)->toBe('Afgewezen');
    expect($keyword->status_color)->toBe('danger');
});

<?php

namespace Dashed\DashedMarketing\Models;

use Illuminate\Database\Eloquent\Model;

class KeywordImport extends Model
{
    protected $table = 'dashed__keyword_imports';

    protected $fillable = [
        'filename',
        'locale',
        'column_mapping',
        'row_count',
        'imported_by',
    ];

    protected $casts = [
        'column_mapping' => 'array',
        'row_count' => 'integer',
    ];
}

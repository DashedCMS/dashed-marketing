<?php

namespace Dashed\DashedMarketing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KeywordImport extends Model
{
    protected $table = 'dashed__keyword_imports';

    protected $fillable = [
        'keyword_research_id',
        'filename',
        'column_mapping',
        'row_count',
        'imported_by',
    ];

    protected $casts = [
        'column_mapping' => 'array',
        'row_count' => 'integer',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(KeywordResearch::class, 'keyword_research_id');
    }
}

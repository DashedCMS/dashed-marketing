<?php

namespace Dashed\DashedMarketing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoAuditPageAnalysis extends Model
{
    protected $table = 'dashed__seo_audit_page_analysis';

    protected $fillable = [
        'audit_id',
        'headings_structure', 'content_length',
        'keyword_density', 'alt_text_coverage',
        'readability_score', 'notes',
    ];

    protected $casts = [
        'headings_structure' => 'array',
        'keyword_density' => 'array',
        'alt_text_coverage' => 'array',
        'content_length' => 'integer',
        'readability_score' => 'integer',
    ];

    public function audit(): BelongsTo
    {
        return $this->belongsTo(SeoAudit::class, 'audit_id');
    }
}

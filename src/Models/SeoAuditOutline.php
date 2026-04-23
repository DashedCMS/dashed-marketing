<?php

namespace Dashed\DashedMarketing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoAuditOutline extends Model
{
    protected $table = 'dashed__seo_audit_outlines';

    protected $fillable = [
        'audit_id',
        'h1',
        'summary',
        'headings',
        'content_generated_at',
    ];

    protected $casts = [
        'headings' => 'array',
        'content_generated_at' => 'datetime',
    ];

    public function audit(): BelongsTo
    {
        return $this->belongsTo(SeoAudit::class, 'audit_id');
    }
}

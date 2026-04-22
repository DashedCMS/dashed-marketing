<?php

namespace Dashed\DashedMarketing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoAuditStructuredDataSuggestion extends Model
{
    protected $table = 'dashed__seo_audit_structured_data_suggestions';

    protected $fillable = [
        'audit_id', 'schema_type', 'json_ld',
        'reason', 'priority', 'status', 'applied_at',
    ];

    protected $casts = ['applied_at' => 'datetime'];

    public function audit(): BelongsTo
    {
        return $this->belongsTo(SeoAudit::class, 'audit_id');
    }
}

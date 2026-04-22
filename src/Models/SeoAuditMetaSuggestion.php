<?php

namespace Dashed\DashedMarketing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoAuditMetaSuggestion extends Model
{
    protected $table = 'dashed__seo_audit_meta_suggestions';

    protected $fillable = [
        'audit_id', 'field',
        'current_value', 'suggested_value',
        'reason', 'priority', 'status', 'applied_at',
    ];

    protected $casts = ['applied_at' => 'datetime'];

    public function audit(): BelongsTo
    {
        return $this->belongsTo(SeoAudit::class, 'audit_id');
    }
}

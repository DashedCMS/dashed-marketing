<?php

namespace Dashed\DashedMarketing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoAuditBlockSuggestion extends Model
{
    protected $table = 'dashed__seo_audit_block_suggestions';

    protected $fillable = [
        'audit_id', 'block_index', 'block_key', 'block_type', 'field_key',
        'is_new_block', 'current_value', 'suggested_value',
        'reason', 'priority', 'status', 'applied_at',
    ];

    protected $casts = [
        'is_new_block' => 'boolean',
        'applied_at' => 'datetime',
    ];

    public function audit(): BelongsTo
    {
        return $this->belongsTo(SeoAudit::class, 'audit_id');
    }
}

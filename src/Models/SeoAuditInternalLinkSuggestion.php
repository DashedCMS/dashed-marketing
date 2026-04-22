<?php

namespace Dashed\DashedMarketing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoAuditInternalLinkSuggestion extends Model
{
    protected $table = 'dashed__seo_audit_internal_link_suggestions';

    protected $fillable = [
        'audit_id', 'anchor_text', 'target_url',
        'target_subject_type', 'target_subject_id',
        'context_description', 'reason',
        'priority', 'status',
    ];

    public function audit(): BelongsTo
    {
        return $this->belongsTo(SeoAudit::class, 'audit_id');
    }
}

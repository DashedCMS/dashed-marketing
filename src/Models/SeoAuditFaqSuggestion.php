<?php

namespace Dashed\DashedMarketing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoAuditFaqSuggestion extends Model
{
    protected $table = 'dashed__seo_audit_faq_suggestions';

    protected $fillable = [
        'audit_id', 'sort_order',
        'question', 'answer', 'target_keyword',
        'priority', 'status', 'applied_at',
    ];

    protected $casts = ['applied_at' => 'datetime'];

    public function audit(): BelongsTo
    {
        return $this->belongsTo(SeoAudit::class, 'audit_id');
    }
}

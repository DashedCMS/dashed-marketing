<?php

namespace Dashed\DashedMarketing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoAuditKeyword extends Model
{
    protected $table = 'dashed__seo_audit_keywords';

    protected $fillable = [
        'audit_id', 'keyword', 'type', 'intent',
        'volume_indication', 'priority', 'notes',
    ];

    public function audit(): BelongsTo
    {
        return $this->belongsTo(SeoAudit::class, 'audit_id');
    }
}

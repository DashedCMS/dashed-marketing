<?php

namespace Dashed\DashedMarketing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentApplyLog extends Model
{
    protected $table = 'dashed__content_apply_logs';

    public $timestamps = false;

    protected $fillable = [
        'seo_improvement_id',
        'content_draft_id',
        'subject_type',
        'subject_id',
        'field_key',
        'previous_value',
        'new_value',
        'applied_by',
        'applied_at',
        'reverted_at',
    ];

    protected $casts = [
        'applied_at' => 'datetime',
        'reverted_at' => 'datetime',
    ];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function improvement(): BelongsTo
    {
        return $this->belongsTo(SeoImprovement::class, 'seo_improvement_id');
    }

    public function draft(): BelongsTo
    {
        return $this->belongsTo(ContentDraft::class, 'content_draft_id');
    }
}

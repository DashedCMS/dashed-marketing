<?php

namespace Dashed\DashedMarketing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ContentDraftLinkCandidate extends Model
{
    protected $table = 'dashed__content_draft_link_candidates';

    protected $fillable = [
        'content_draft_id',
        'sort_order',
        'type',
        'title',
        'url',
        'subject_type',
        'subject_id',
    ];

    public function contentDraft(): BelongsTo
    {
        return $this->belongsTo(ContentDraft::class, 'content_draft_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}

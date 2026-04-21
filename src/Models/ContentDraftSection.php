<?php

namespace Dashed\DashedMarketing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentDraftSection extends Model
{
    protected $table = 'dashed__content_draft_sections';

    protected $fillable = [
        'content_draft_id',
        'sort_order',
        'heading',
        'intent',
        'body',
        'error_message',
    ];

    public function contentDraft(): BelongsTo
    {
        return $this->belongsTo(ContentDraft::class, 'content_draft_id');
    }
}

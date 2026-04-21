<?php

namespace Dashed\DashedMarketing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentDraftFaq extends Model
{
    protected $table = 'dashed__content_draft_faqs';

    protected $fillable = [
        'content_draft_id',
        'sort_order',
        'question',
        'answer',
    ];

    public function contentDraft(): BelongsTo
    {
        return $this->belongsTo(ContentDraft::class, 'content_draft_id');
    }
}

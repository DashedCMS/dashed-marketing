<?php

namespace Dashed\DashedMarketing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ContentEmbedding extends Model
{
    protected $table = 'dashed__content_embeddings';

    protected $fillable = [
        'embeddable_type',
        'embeddable_id',
        'vector',
        'content_hash',
    ];

    protected $casts = [
        'vector' => 'array',
    ];

    public function embeddable(): MorphTo
    {
        return $this->morphTo();
    }
}

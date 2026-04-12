<?php

namespace Dashed\DashedMarketing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentDraft extends Model
{
    protected $table = 'dashed__content_drafts';

    protected $fillable = [
        'content_cluster_id',
        'keyword',
        'locale',
        'instruction',
        'status',
        'progress_message',
        'error_message',
        'content_plan',
        'article_content',
        'subject_type',
        'subject_id',
        'applied_by',
        'applied_at',
    ];

    protected $casts = [
        'content_plan' => 'array',
        'article_content' => 'array',
        'applied_at' => 'datetime',
    ];

    public function contentCluster(): BelongsTo
    {
        return $this->belongsTo(ContentCluster::class, 'content_cluster_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function setProgress(string $message): void
    {
        $this->update(['progress_message' => $message]);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'In wachtrij',
            'planning' => 'Planning...',
            'writing' => 'Schrijven...',
            'ready' => 'Klaar',
            'applied' => 'Toegepast',
            'failed' => 'Mislukt',
            default => $this->status,
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pending', 'planning', 'writing' => 'warning',
            'ready' => 'success',
            'applied' => 'primary',
            'failed' => 'danger',
            default => 'gray',
        };
    }
}

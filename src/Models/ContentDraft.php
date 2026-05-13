<?php

namespace Dashed\DashedMarketing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ContentDraft extends Model
{
    protected $table = 'dashed__content_drafts';

    protected $fillable = [
        'content_cluster_id',
        'name',
        'slug',
        'meta_title',
        'meta_description',
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
        'history',
    ];

    protected $casts = [
        'content_plan' => 'array',
        'article_content' => 'array',
        'applied_at' => 'datetime',
        'history' => 'array',
    ];

    public function contentCluster(): BelongsTo
    {
        return $this->belongsTo(ContentCluster::class, 'content_cluster_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function keywords(): BelongsToMany
    {
        return $this->belongsToMany(
            Keyword::class,
            'dashed__content_draft_keyword',
            'content_draft_id',
            'keyword_id',
        );
    }

    public function sections(): HasMany
    {
        return $this->hasMany(ContentDraftSection::class, 'content_draft_id')->orderBy('sort_order');
    }

    public function faqs(): HasMany
    {
        return $this->hasMany(ContentDraftFaq::class, 'content_draft_id')->orderBy('sort_order');
    }

    public function linkCandidates(): HasMany
    {
        return $this->hasMany(ContentDraftLinkCandidate::class, 'content_draft_id')->orderBy('sort_order');
    }

    public function setProgress(string $message): void
    {
        $this->update(['progress_message' => $message]);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'concept' => 'Concept',
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
            'concept' => 'gray',
            'pending', 'planning', 'writing' => 'warning',
            'ready' => 'success',
            'applied' => 'primary',
            'failed' => 'danger',
            default => 'gray',
        };
    }

    public function pushHistory(array $snapshot): void
    {
        $history = $this->history ?? [];
        array_unshift($history, [
            'at' => now()->toIso8601String(),
            'sections' => $snapshot,
        ]);
        $this->history = array_slice($history, 0, 3);
        $this->save();
    }
}

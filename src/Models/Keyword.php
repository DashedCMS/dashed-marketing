<?php

namespace Dashed\DashedMarketing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Keyword extends Model
{
    protected $table = 'dashed__keywords';

    protected $fillable = [
        'keyword_research_id',
        'keyword',
        'type',
        'search_intent',
        'difficulty',
        'volume_indication',
        'status',
        'notes',
    ];

    public function keywordResearch(): BelongsTo
    {
        return $this->belongsTo(KeywordResearch::class, 'keyword_research_id');
    }

    public function contentClusters(): BelongsToMany
    {
        return $this->belongsToMany(ContentCluster::class, 'dashed__content_cluster_keyword', 'keyword_id', 'content_cluster_id');
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'primary' => 'Primair',
            'secondary' => 'Secundair',
            'long_tail' => 'Long-tail',
            'lsi' => 'LSI / semantisch',
            'question' => 'Vraag',
            default => $this->type,
        };
    }

    public function getTypeColorAttribute(): string
    {
        return match ($this->type) {
            'primary' => 'danger',
            'secondary' => 'warning',
            'long_tail' => 'info',
            'lsi' => 'gray',
            'question' => 'success',
            default => 'gray',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'new' => 'Nieuw',
            'approved' => 'Goedgekeurd',
            'blacklisted' => 'Geblacklist',
            default => $this->status,
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'new' => 'gray',
            'approved' => 'success',
            'blacklisted' => 'danger',
            default => 'gray',
        };
    }

    public function getDifficultyColorAttribute(): string
    {
        return match ($this->difficulty) {
            'easy' => 'success',
            'medium' => 'warning',
            'hard' => 'danger',
            default => 'gray',
        };
    }

    public function getVolumeColorAttribute(): string
    {
        return match ($this->volume_indication) {
            'low' => 'gray',
            'medium' => 'warning',
            'high' => 'success',
            default => 'gray',
        };
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }
}

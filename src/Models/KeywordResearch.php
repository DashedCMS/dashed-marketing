<?php

namespace Dashed\DashedMarketing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KeywordResearch extends Model
{
    protected $table = 'dashed__keyword_researches';

    protected $fillable = [
        'seed_keyword',
        'locale',
        'status',
        'progress_message',
        'error_message',
    ];

    public function keywords(): HasMany
    {
        return $this->hasMany(Keyword::class, 'keyword_research_id');
    }

    public function contentClusters(): HasMany
    {
        return $this->hasMany(ContentCluster::class, 'keyword_research_id');
    }

    public function setProgress(string $message): void
    {
        $this->update(['progress_message' => $message]);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'In wachtrij',
            'running' => 'Bezig...',
            'done' => 'Klaar',
            'failed' => 'Mislukt',
            default => $this->status,
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pending', 'running' => 'warning',
            'done' => 'success',
            'failed' => 'danger',
            default => 'gray',
        };
    }
}

<?php

namespace Dashed\DashedMarketing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SeoImprovement extends Model
{
    protected $table = 'dashed__seo_improvements';

    protected $fillable = [
        'subject_type',
        'subject_id',
        'status',
        'keyword_research',
        'analysis_summary',
        'field_proposals',
        'block_proposals',
        'block_proposals_status',
        'error_message',
        'progress_message',
        'created_by',
        'applied_by',
        'applied_at',
    ];

    protected $casts = [
        'keyword_research' => 'array',
        'field_proposals' => 'array',
        'block_proposals' => 'array',
        'applied_at' => 'datetime',
        'block_proposals_status' => 'array',
    ];

    public function setProgress(string $message): void
    {
        $this->update(['progress_message' => $message]);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'analyzing' => 'Bezig met analyseren...',
            'ready' => 'Klaar voor review',
            'applied' => 'Toegepast',
            'failed' => 'Mislukt',
            default => $this->status,
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'analyzing' => 'warning',
            'ready' => 'success',
            'applied' => 'primary',
            'failed' => 'danger',
            default => 'gray',
        };
    }

    public function markProposal(string $key, string $status): void
    {
        $statuses = $this->block_proposals_status ?? [];
        $statuses[$key] = $status;
        $this->block_proposals_status = $statuses;
        $this->save();
    }

    public function proposalStatus(string $key): string
    {
        return ($this->block_proposals_status ?? [])[$key] ?? 'pending';
    }
}

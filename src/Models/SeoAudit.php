<?php

namespace Dashed\DashedMarketing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SeoAudit extends Model
{
    protected $table = 'dashed__seo_audits';

    protected $fillable = [
        'subject_type', 'subject_id',
        'status', 'overall_score', 'score_breakdown',
        'analysis_summary', 'progress_message', 'error_message',
        'instruction', 'locale',
        'created_by', 'applied_by', 'applied_at', 'archived_at',
    ];

    protected $casts = [
        'score_breakdown' => 'array',
        'applied_at' => 'datetime',
        'archived_at' => 'datetime',
        'overall_score' => 'integer',
    ];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function metaSuggestions(): HasMany
    {
        return $this->hasMany(SeoAuditMetaSuggestion::class, 'audit_id');
    }

    public function blockSuggestions(): HasMany
    {
        return $this->hasMany(SeoAuditBlockSuggestion::class, 'audit_id')
            ->orderBy('block_index')->orderBy('field_key');
    }

    public function faqSuggestions(): HasMany
    {
        return $this->hasMany(SeoAuditFaqSuggestion::class, 'audit_id')->orderBy('sort_order');
    }

    public function structuredDataSuggestions(): HasMany
    {
        return $this->hasMany(SeoAuditStructuredDataSuggestion::class, 'audit_id');
    }

    public function internalLinkSuggestions(): HasMany
    {
        return $this->hasMany(SeoAuditInternalLinkSuggestion::class, 'audit_id');
    }

    public function keywords(): HasMany
    {
        return $this->hasMany(SeoAuditKeyword::class, 'audit_id');
    }

    public function pageAnalysis(): HasOne
    {
        return $this->hasOne(SeoAuditPageAnalysis::class, 'audit_id');
    }

    public function outline(): HasOne
    {
        return $this->hasOne(SeoAuditOutline::class, 'audit_id');
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', ['archived', 'failed']);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'analyzing' => 'Bezig met analyseren',
            'ready' => 'Klaar voor review',
            'partially_applied' => 'Gedeeltelijk toegepast',
            'fully_applied' => 'Volledig toegepast',
            'archived' => 'Gearchiveerd',
            'failed' => 'Mislukt',
            default => $this->status,
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'analyzing' => 'warning',
            'ready' => 'success',
            'partially_applied' => 'info',
            'fully_applied' => 'primary',
            'archived' => 'gray',
            'failed' => 'danger',
            default => 'gray',
        };
    }
}

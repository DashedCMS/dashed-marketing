<?php

namespace Dashed\DashedMarketing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ContentCluster extends Model
{
    protected $table = 'dashed__content_clusters';

    protected $fillable = [
        'name',
        'locale',
        'theme',
        'content_type',
        'description',
        'status',
        'pending_concepts',
    ];

    protected $casts = [
        'pending_concepts' => 'array',
    ];

    public function scopeForLocale($query, string $locale)
    {
        return $query->where('locale', $locale);
    }

    public function keywords(): BelongsToMany
    {
        return $this->belongsToMany(Keyword::class, 'dashed__content_cluster_keyword', 'content_cluster_id', 'keyword_id');
    }

    public function contentDrafts(): HasMany
    {
        return $this->hasMany(ContentDraft::class, 'content_cluster_id');
    }

    public function getContentTypeLabelAttribute(): string
    {
        return match ($this->content_type) {
            'blog' => 'Blog',
            'landing_page' => 'Landingspagina',
            'category' => 'Categoriepagina',
            'faq' => 'FAQ pagina',
            'product' => 'Productpagina',
            'other' => 'Anders',
            default => $this->content_type,
        };
    }

    public function getContentTypeColorAttribute(): string
    {
        return match ($this->content_type) {
            'blog' => 'info',
            'landing_page' => 'success',
            'category' => 'warning',
            'faq' => 'primary',
            'product' => 'danger',
            default => 'gray',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'planned' => 'Gepland',
            'in_progress' => 'In uitvoering',
            'done' => 'Klaar',
            default => $this->status,
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'planned' => 'gray',
            'in_progress' => 'warning',
            'done' => 'success',
            default => 'gray',
        };
    }
}

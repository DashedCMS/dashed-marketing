<?php

namespace Dashed\DashedMarketing\Models;

use Dashed\DashedCore\Classes\Sites;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialIdea extends Model
{
    protected $table = 'dashed__social_ideas';

    protected $fillable = [
        'site_id',
        'title',
        'platform',
        'pillar_id',
        'notes',
        'status',
        'tags',
    ];

    protected $casts = [
        'tags' => 'array',
    ];

    public const STATUSES = [
        'idea' => 'Idee',
        'in_production' => 'In productie',
        'used' => 'Gebruikt',
        'archived' => 'Archief',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('site', function ($query) {
            $query->where('site_id', Sites::getActive());
        });

        static::creating(function ($model) {
            $model->site_id = $model->site_id ?? Sites::getActive();
        });
    }

    public function pillar(): BelongsTo
    {
        return $this->belongsTo(SocialPillar::class, 'pillar_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}

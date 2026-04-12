<?php

namespace Dashed\DashedMarketing\Models;

use Dashed\DashedCore\Classes\Sites;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocialPillar extends Model
{
    protected $table = 'dashed__social_pillars';

    protected $fillable = [
        'site_id',
        'name',
        'description',
        'target_percentage',
        'color',
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

    public function posts(): HasMany
    {
        return $this->hasMany(SocialPost::class, 'pillar_id');
    }

    public function ideas(): HasMany
    {
        return $this->hasMany(SocialIdea::class, 'pillar_id');
    }
}

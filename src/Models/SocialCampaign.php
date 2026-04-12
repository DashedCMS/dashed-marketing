<?php

namespace Dashed\DashedMarketing\Models;

use Dashed\DashedCore\Classes\Sites;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocialCampaign extends Model
{
    protected $table = 'dashed__social_campaigns';

    protected $fillable = [
        'site_id',
        'name',
        'start_date',
        'end_date',
        'focus',
        'active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'active' => 'boolean',
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
        return $this->hasMany(SocialPost::class, 'campaign_id');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true)
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now());
    }
}

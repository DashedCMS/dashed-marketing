<?php

namespace Dashed\DashedMarketing\Models;

use Dashed\DashedCore\Classes\Sites;
use Illuminate\Database\Eloquent\Model;

class SocialChannel extends Model
{
    protected $table = 'dashed__social_channels';

    protected $fillable = [
        'site_id',
        'name',
        'slug',
        'accepted_types',
        'meta',
        'order',
        'is_active',
    ];

    protected $casts = [
        'accepted_types' => 'array',
        'meta' => 'array',
        'is_active' => 'boolean',
        'order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('site', function ($query) {
            $query->where('site_id', Sites::getActive());
        });

        static::creating(function (SocialChannel $model) {
            $model->site_id = $model->site_id ?? Sites::getActive();
        });
    }
}

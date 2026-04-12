<?php

namespace Dashed\DashedMarketing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialPostVersion extends Model
{
    protected $table = 'dashed__social_post_versions';

    public $timestamps = false;

    protected $fillable = [
        'post_id',
        'caption',
        'notes',
        'created_by',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(SocialPost::class, 'post_id');
    }
}

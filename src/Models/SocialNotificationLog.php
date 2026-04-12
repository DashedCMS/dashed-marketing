<?php

namespace Dashed\DashedMarketing\Models;

use Illuminate\Database\Eloquent\Model;

class SocialNotificationLog extends Model
{
    protected $table = 'dashed__social_notification_log';

    public $timestamps = false;

    protected $fillable = [
        'type',
        'site_id',
        'sent_at',
        'recipient',
        'content',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];
}

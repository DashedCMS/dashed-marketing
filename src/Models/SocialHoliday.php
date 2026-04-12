<?php

namespace Dashed\DashedMarketing\Models;

use Illuminate\Database\Eloquent\Model;

class SocialHoliday extends Model
{
    protected $table = 'dashed__social_holidays';

    protected $fillable = [
        'name',
        'date',
        'country',
        'auto_remind',
        'remind_days_before',
    ];

    protected $casts = [
        'date' => 'date',
        'auto_remind' => 'boolean',
    ];

    public function scopeUpcoming($query, int $days = 30)
    {
        return $query->where('date', '>=', now())
            ->where('date', '<=', now()->addDays($days))
            ->orderBy('date');
    }

    public function scopeNeedsReminder($query)
    {
        return $query->where('auto_remind', true)
            ->whereRaw('DATE_SUB(date, INTERVAL remind_days_before DAY) <= ?', [now()->toDateString()])
            ->where('date', '>=', now());
    }
}

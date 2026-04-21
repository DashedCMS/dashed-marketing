<?php

namespace Dashed\DashedMarketing\Models;

use Dashed\DashedCore\Classes\Sites;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SocialPost extends Model
{
    protected $table = 'dashed__social_posts';

    protected $fillable = [
        'site_id',
        'platform',
        'type',
        'channels',
        'status',
        'caption',
        'image_path',
        'images',
        'scheduled_at',
        'posted_at',
        'post_url',
        'channel_captions',
        'captions_per_channel',
        'ratio_images',
        'external_id',
        'external_data',
        'failed_platforms',
        'published_urls',
        'retry_count',
        'analytics_synced_at',
        'pillar_id',
        'subject_type',
        'subject_id',
        'campaign_id',
        'performance_data',
        'hashtags',
        'alt_text',
        'image_prompt',
        'created_by',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'posted_at' => 'datetime',
        'performance_data' => 'array',
        'hashtags' => 'array',
        'channels' => 'array',
        'images' => 'array',
        'channel_captions' => 'array',
        'captions_per_channel' => 'boolean',
        'ratio_images' => 'array',
        'external_data' => 'array',
        'failed_platforms' => 'array',
        'published_urls' => 'array',
        'retry_count' => 'integer',
        'analytics_synced_at' => 'datetime',
    ];

    public const STATUSES = [
        'concept' => 'Concept',
        'in_review' => 'In review',
        'approved' => 'Goedgekeurd',
        'scheduled' => 'Ingepland',
        'posted' => 'Gepost',
        'archived' => 'Archief',
        'publishing' => 'Publiceren...',
        'partially_posted' => 'Deels gepost',
        'publish_failed' => 'Publicatie mislukt',
    ];

    public const STATUS_COLORS = [
        'concept' => 'gray',
        'in_review' => 'warning',
        'approved' => 'info',
        'scheduled' => 'purple',
        'posted' => 'success',
        'archived' => 'danger',
        'publishing' => 'warning',
        'partially_posted' => 'danger',
        'publish_failed' => 'danger',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('site', function ($query) {
            $query->where('site_id', Sites::getActive());
        });

        static::creating(function ($model) {
            $model->site_id = $model->site_id ?? Sites::getActive();
        });

        static::saving(function ($model) {
            $hasFutureSchedule = $model->scheduled_at && $model->scheduled_at->isFuture();
            $inPlannableStatus = in_array($model->status, ['concept', 'approved'], true);

            if ($hasFutureSchedule && $inPlannableStatus) {
                $model->status = 'scheduled';
            } elseif (! $model->scheduled_at && $model->status === 'scheduled') {
                $model->status = 'concept';
            }
        });
    }

    public function pillar(): BelongsTo
    {
        return $this->belongsTo(SocialPillar::class, 'pillar_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(SocialCampaign::class, 'campaign_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function versions(): HasMany
    {
        return $this->hasMany(SocialPostVersion::class, 'post_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        return self::STATUS_COLORS[$this->status] ?? 'gray';
    }

    public function getPlatformLabelAttribute(): string
    {
        return config("dashed-marketing.platforms.{$this->platform}.label", $this->platform);
    }

    public function isOverdue(): bool
    {
        return $this->status === 'scheduled'
            && $this->scheduled_at
            && $this->scheduled_at->isPast();
    }

    public function markAsPosted(?string $postUrl = null): void
    {
        $this->update([
            'status' => 'posted',
            'posted_at' => now(),
            'post_url' => $postUrl,
        ]);
    }
}

<?php

namespace Dashed\DashedMarketing\Database\Seeders;

use Dashed\DashedMarketing\Models\SocialChannel;
use Illuminate\Database\Seeder;

class SocialChannelSeeder extends Seeder
{
    public function seedSite(string $siteId): void
    {
        $channels = config('dashed-marketing.channels', []);

        $order = 0;
        foreach ($channels as $slug => $channel) {
            $order++;

            SocialChannel::query()
                ->withoutGlobalScopes()
                ->updateOrCreate(
                    [
                        'site_id' => $siteId,
                        'slug' => $slug,
                    ],
                    [
                        'name' => $channel['label'] ?? ucfirst(str_replace('_', ' ', $slug)),
                        'accepted_types' => $channel['accepted_types'] ?? ['post'],
                        'meta' => [
                            'caption_min' => $channel['caption_min'] ?? 0,
                            'caption_max' => $channel['caption_max'] ?? 0,
                            'hashtags_min' => $channel['hashtags_min'] ?? 0,
                            'hashtags_max' => $channel['hashtags_max'] ?? 0,
                            'tips' => $channel['tips'] ?? '',
                        ],
                        'order' => $order,
                        'is_active' => true,
                    ]
                );
        }
    }
}

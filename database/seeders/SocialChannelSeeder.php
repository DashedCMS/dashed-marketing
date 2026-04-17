<?php

namespace Dashed\DashedMarketing\Database\Seeders;

use Illuminate\Database\Seeder;
use Dashed\DashedMarketing\Models\SocialChannel;

class SocialChannelSeeder extends Seeder
{
    public const DEFAULTS = [
        'facebook_page' => ['label' => 'Facebook Page', 'accepted_types' => ['post', 'reel', 'story'], 'caption_min' => 50, 'caption_max' => 500, 'hashtags_min' => 0, 'hashtags_max' => 3, 'tips' => 'Vraag of CTA, conversational, links zijn toegestaan.'],
        'facebook_group' => ['label' => 'Facebook Group', 'accepted_types' => ['post'], 'caption_min' => 50, 'caption_max' => 500, 'hashtags_min' => 0, 'hashtags_max' => 0, 'tips' => 'Geen sales, community-toon, vraag of discussie.'],
        'instagram_feed' => ['label' => 'Instagram Feed', 'accepted_types' => ['post'], 'caption_min' => 125, 'caption_max' => 300, 'hashtags_min' => 10, 'hashtags_max' => 15, 'tips' => 'Hook in eerste zin, witregels voor scanbaarheid, mix van brede en niche hashtags.'],
        'instagram_reels' => ['label' => 'Instagram Reels', 'accepted_types' => ['reel'], 'caption_min' => 20, 'caption_max' => 100, 'hashtags_min' => 3, 'hashtags_max' => 5, 'tips' => 'Hook binnen 3 sec, korte caption die de hook versterkt.'],
        'instagram_story' => ['label' => 'Instagram Story', 'accepted_types' => ['story'], 'caption_min' => 0, 'caption_max' => 0, 'hashtags_min' => 0, 'hashtags_max' => 0, 'tips' => 'Polls, vragen-stickers, link-sticker, spreektaal.'],
        'linkedin_personal' => ['label' => 'LinkedIn (persoonlijk)', 'accepted_types' => ['post'], 'caption_min' => 100, 'caption_max' => 1300, 'hashtags_min' => 3, 'hashtags_max' => 5, 'tips' => 'Persoonlijk verhaal of inzicht, geen sales, eerste 2 regels = hook.'],
        'linkedin_company' => ['label' => 'LinkedIn (bedrijf)', 'accepted_types' => ['post'], 'caption_min' => 100, 'caption_max' => 1300, 'hashtags_min' => 3, 'hashtags_max' => 5, 'tips' => 'Zakelijk maar menselijk, expertise tonen, vermijd corporate jargon.'],
        'tiktok' => ['label' => 'TikTok', 'accepted_types' => ['reel'], 'caption_min' => 10, 'caption_max' => 80, 'hashtags_min' => 3, 'hashtags_max' => 5, 'tips' => 'Hook + trending audio, geen externe links.'],
        'youtube_shorts' => ['label' => 'YouTube Shorts', 'accepted_types' => ['reel'], 'caption_min' => 20, 'caption_max' => 100, 'hashtags_min' => 3, 'hashtags_max' => 5, 'tips' => 'Title-driven, hook + payoff, eindscherm-CTA.'],
        'pinterest' => ['label' => 'Pinterest', 'accepted_types' => ['post', 'reel'], 'caption_min' => 150, 'caption_max' => 300, 'hashtags_min' => 0, 'hashtags_max' => 5, 'tips' => 'Long-tail keywords in titel en beschrijving, evergreen content.'],
        'x' => ['label' => 'X (Twitter)', 'accepted_types' => ['post'], 'caption_min' => 0, 'caption_max' => 280, 'hashtags_min' => 0, 'hashtags_max' => 2, 'tips' => 'Scherp, kort, een gedachte. Hashtags zelden nodig.'],
        'threads' => ['label' => 'Threads', 'accepted_types' => ['post'], 'caption_min' => 0, 'caption_max' => 500, 'hashtags_min' => 0, 'hashtags_max' => 0, 'tips' => 'Conversational, geen hashtags, korte zinnen.'],
        'bluesky' => ['label' => 'Bluesky', 'accepted_types' => ['post'], 'caption_min' => 0, 'caption_max' => 300, 'hashtags_min' => 0, 'hashtags_max' => 2, 'tips' => 'Persoonlijk, indie-vibes, geen sales.'],
        'google_business' => ['label' => 'Google Business Profile', 'accepted_types' => ['post'], 'caption_min' => 80, 'caption_max' => 1500, 'hashtags_min' => 0, 'hashtags_max' => 0, 'tips' => 'Lokale focus, openingstijden, aanbiedingen of evenementen.'],
    ];

    public function seedSite(string $siteId): void
    {
        $order = 0;
        foreach (self::DEFAULTS as $slug => $channel) {
            $order++;

            SocialChannel::query()
                ->withoutGlobalScopes()
                ->updateOrCreate(
                    [
                        'site_id' => $siteId,
                        'slug' => $slug,
                    ],
                    [
                        'name' => $channel['label'],
                        'accepted_types' => $channel['accepted_types'],
                        'meta' => [
                            'caption_min' => $channel['caption_min'],
                            'caption_max' => $channel['caption_max'],
                            'hashtags_min' => $channel['hashtags_min'],
                            'hashtags_max' => $channel['hashtags_max'],
                            'tips' => $channel['tips'],
                        ],
                        'order' => $order,
                        'is_active' => true,
                    ]
                );
        }
    }
}

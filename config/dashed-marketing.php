<?php

use Dashed\DashedMarketing\Adapters\ManualPublishAdapter;

return [
    'adapters' => [
        'keyword_research' => null,
        'publishing' => ManualPublishAdapter::class,
    ],
    /*
     * @deprecated — use 'types' + 'channels' instead. Kept temporarily so legacy
     * call sites keep working until they're migrated to the new model.
     */
    'platforms' => [
        'instagram_feed' => ['label' => 'Instagram Feed', 'caption_min' => 125, 'caption_max' => 300, 'hashtags_min' => 10, 'hashtags_max' => 15, 'ratios' => ['4:5', '1:1'], 'tips' => 'Hook in eerste zin'],
        'instagram_reels' => ['label' => 'Instagram Reels', 'caption_min' => 20, 'caption_max' => 100, 'hashtags_min' => 3, 'hashtags_max' => 5, 'ratios' => ['9:16'], 'tips' => 'Hook binnen 3 sec'],
        'pinterest' => ['label' => 'Pinterest', 'caption_min' => 150, 'caption_max' => 300, 'hashtags_min' => 5, 'hashtags_max' => 10, 'ratios' => ['2:3'], 'tips' => 'Long-tail keywords in beschrijving'],
        'facebook' => ['label' => 'Facebook', 'caption_min' => 50, 'caption_max' => 500, 'hashtags_min' => 2, 'hashtags_max' => 3, 'ratios' => ['1:1', '16:9'], 'tips' => 'Vraag of CTA'],
        'tiktok' => ['label' => 'TikTok', 'caption_min' => 10, 'caption_max' => 80, 'hashtags_min' => 3, 'hashtags_max' => 5, 'ratios' => ['9:16'], 'tips' => 'Hook + trend suggestie'],
    ],

    /*
     * Post types — how the content is structured. Each type has a default ratio set
     * and is independent of the channels it gets published on.
     */
    'types' => [
        'post' => [
            'label' => 'Post',
            'icon' => 'heroicon-o-photo',
            'description' => 'Eén afbeelding met caption',
            'ratios' => ['4:5', '1:1'],
            'tips' => 'Hook in de eerste zin, sterke visual, één duidelijke CTA.',
        ],
        'reel' => [
            'label' => 'Reel / Short',
            'icon' => 'heroicon-o-film',
            'description' => 'Verticale korte video (9:16), 5–60 seconden',
            'ratios' => ['9:16'],
            'tips' => 'Hook binnen 3 seconden, trending audio, tekst-overlay voor mute viewers.',
        ],
        'story' => [
            'label' => 'Story',
            'icon' => 'heroicon-o-bookmark',
            'description' => 'Verticale ephemeral content (24u zichtbaar)',
            'ratios' => ['9:16'],
            'tips' => 'Polls, vragen-stickers, swipe-up of link-sticker.',
        ],
    ],

    /*
     * Channels — where a post can be published. Each channel declares which types
     * it accepts plus per-channel caption/hashtag limits and tips.
     */
    'channels' => [
        'facebook_page' => ['label' => 'Facebook Page', 'accepted_types' => ['post', 'reel', 'story'], 'caption_min' => 50, 'caption_max' => 500, 'hashtags_min' => 0, 'hashtags_max' => 3, 'tips' => 'Vraag of CTA, conversational, links zijn toegestaan.'],
        'facebook_group' => ['label' => 'Facebook Group', 'accepted_types' => ['post'], 'caption_min' => 50, 'caption_max' => 500, 'hashtags_min' => 0, 'hashtags_max' => 0, 'tips' => 'Geen sales, community-toon, vraag of discussie.'],
        'instagram_feed' => ['label' => 'Instagram Feed', 'accepted_types' => ['post'], 'caption_min' => 125, 'caption_max' => 300, 'hashtags_min' => 10, 'hashtags_max' => 15, 'tips' => 'Hook in eerste zin, witregels voor scanbaarheid, mix van brede en niche hashtags.'],
        'instagram_reels' => ['label' => 'Instagram Reels', 'accepted_types' => ['reel'], 'caption_min' => 20, 'caption_max' => 100, 'hashtags_min' => 3, 'hashtags_max' => 5, 'tips' => 'Hook binnen 3 sec, korte caption die de hook versterkt.'],
        'instagram_story' => ['label' => 'Instagram Story', 'accepted_types' => ['story'], 'caption_min' => 0, 'caption_max' => 0, 'hashtags_min' => 0, 'hashtags_max' => 0, 'tips' => 'Polls, vragen-stickers, link-sticker, spreektaal.'],
        'linkedin_personal' => ['label' => 'LinkedIn (persoonlijk)', 'accepted_types' => ['post'], 'caption_min' => 100, 'caption_max' => 1300, 'hashtags_min' => 3, 'hashtags_max' => 5, 'tips' => 'Persoonlijk verhaal of inzicht, geen sales, eerste 2 regels = hook.'],
        'linkedin_company' => ['label' => 'LinkedIn (bedrijf)', 'accepted_types' => ['post'], 'caption_min' => 100, 'caption_max' => 1300, 'hashtags_min' => 3, 'hashtags_max' => 5, 'tips' => 'Zakelijk maar menselijk, expertise tonen, vermijd corporate jargon.'],
        'tiktok' => ['label' => 'TikTok', 'accepted_types' => ['reel'], 'caption_min' => 10, 'caption_max' => 80, 'hashtags_min' => 3, 'hashtags_max' => 5, 'tips' => 'Hook + trending audio, geen externe links.'],
        'youtube_shorts' => ['label' => 'YouTube Shorts', 'accepted_types' => ['reel'], 'caption_min' => 20, 'caption_max' => 100, 'hashtags_min' => 3, 'hashtags_max' => 5, 'tips' => 'Title-driven, hook + payoff, eindscherm-CTA.'],
        'pinterest' => ['label' => 'Pinterest', 'accepted_types' => ['post', 'reel'], 'caption_min' => 150, 'caption_max' => 300, 'hashtags_min' => 0, 'hashtags_max' => 5, 'tips' => 'Long-tail keywords in titel én beschrijving, evergreen content.'],
        'x' => ['label' => 'X (Twitter)', 'accepted_types' => ['post'], 'caption_min' => 0, 'caption_max' => 280, 'hashtags_min' => 0, 'hashtags_max' => 2, 'tips' => 'Scherp, kort, één gedachte. Hashtags zelden nodig.'],
        'threads' => ['label' => 'Threads', 'accepted_types' => ['post'], 'caption_min' => 0, 'caption_max' => 500, 'hashtags_min' => 0, 'hashtags_max' => 0, 'tips' => 'Conversational, geen hashtags, korte zinnen.'],
        'bluesky' => ['label' => 'Bluesky', 'accepted_types' => ['post'], 'caption_min' => 0, 'caption_max' => 300, 'hashtags_min' => 0, 'hashtags_max' => 2, 'tips' => 'Persoonlijk, indie-vibes, geen sales.'],
        'google_business' => ['label' => 'Google Business Profile', 'accepted_types' => ['post'], 'caption_min' => 80, 'caption_max' => 1500, 'hashtags_min' => 0, 'hashtags_max' => 0, 'tips' => 'Lokale focus, openingstijden, aanbiedingen of evenementen.'],
    ],
    'image_generation' => [
        'style_presets' => ['minimalist' => 'Minimalistisch interieur', 'lifestyle' => 'Lifestyle', 'flat_lay' => 'Flat lay', 'moody' => 'Moody', 'bright_airy' => 'Bright & airy'],
        'ratios' => ['4:5', '1:1', '9:16', '2:3'],
        'default_strength' => 0.7,
    ],
    'context_builder' => ['max_tokens' => 8000, 'max_products' => 50],
];

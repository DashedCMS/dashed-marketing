<?php

use Dashed\DashedMarketing\Adapters\ManualPublishAdapter;

return [
    'adapters' => [
        'keyword_research' => null,
        'publishing' => ManualPublishAdapter::class,
    ],
    /*
     * @deprecated - use 'types' (config) + social_channels table (database) instead.
     * Kept temporarily so legacy call sites keep working until they're migrated.
     */
    'platforms' => [
        'instagram_feed' => ['label' => 'Instagram Feed', 'caption_min' => 125, 'caption_max' => 300, 'hashtags_min' => 10, 'hashtags_max' => 15, 'ratios' => ['4:5', '1:1'], 'tips' => 'Hook in eerste zin'],
        'instagram_reels' => ['label' => 'Instagram Reels', 'caption_min' => 20, 'caption_max' => 100, 'hashtags_min' => 3, 'hashtags_max' => 5, 'ratios' => ['9:16'], 'tips' => 'Hook binnen 3 sec'],
        'pinterest' => ['label' => 'Pinterest', 'caption_min' => 150, 'caption_max' => 300, 'hashtags_min' => 5, 'hashtags_max' => 10, 'ratios' => ['2:3'], 'tips' => 'Long-tail keywords in beschrijving'],
        'facebook' => ['label' => 'Facebook', 'caption_min' => 50, 'caption_max' => 500, 'hashtags_min' => 2, 'hashtags_max' => 3, 'ratios' => ['1:1', '16:9'], 'tips' => 'Vraag of CTA'],
        'tiktok' => ['label' => 'TikTok', 'caption_min' => 10, 'caption_max' => 80, 'hashtags_min' => 3, 'hashtags_max' => 5, 'ratios' => ['9:16'], 'tips' => 'Hook + trend suggestie'],
    ],

    /*
     * Post types - how the content is structured. Each type has a default ratio set
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

    'image_generation' => [
        'style_presets' => ['minimalist' => 'Minimalistisch interieur', 'lifestyle' => 'Lifestyle', 'flat_lay' => 'Flat lay', 'moody' => 'Moody', 'bright_airy' => 'Bright & airy'],
        'ratios' => ['4:5', '1:1', '9:16', '2:3'],
        'default_strength' => 0.7,
    ],
    'context_builder' => ['max_tokens' => 8000, 'max_products' => 50],

    /*
    |--------------------------------------------------------------------------
    | SEO Audit - block rewrite whitelist
    |--------------------------------------------------------------------------
    |
    | Per block-type: welke velden mag de AI herschrijven. Andere velden
    | blijven onaangeraakt bij apply. Sleutels moeten matchen met het
    | block-type zoals geregistreerd in cms()->builder('blocks').
    |
    */
    'seo_block_whitelist' => [
        'content' => ['content'],
        'header' => ['title', 'subtitle'],
        'hero' => ['title', 'subtitle'],
        'cta' => ['title', 'subtitle'],
        'text' => ['content'],
        'text_image' => ['content', 'title'],
        'faq' => ['questions', 'faqs'],
    ],

    /*
    |--------------------------------------------------------------------------
    | SEO Audit - FAQ block types
    |--------------------------------------------------------------------------
    |
    | Block types die herkend worden als FAQ-blok voor FAQ apply.
    |
    */
    'seo_faq_block_types' => ['faq'],
];

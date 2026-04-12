<?php

return [
    'adapters' => [
        'keyword_research' => \Dashed\DashedMarketing\Adapters\ClaudeKeywordAdapter::class,
        'publishing' => \Dashed\DashedMarketing\Adapters\ManualPublishAdapter::class,
    ],
    'platforms' => [
        'instagram_feed' => ['label' => 'Instagram Feed', 'caption_min' => 125, 'caption_max' => 300, 'hashtags_min' => 10, 'hashtags_max' => 15, 'ratios' => ['4:5', '1:1'], 'tips' => 'Hook in eerste zin'],
        'instagram_reels' => ['label' => 'Instagram Reels', 'caption_min' => 20, 'caption_max' => 100, 'hashtags_min' => 3, 'hashtags_max' => 5, 'ratios' => ['9:16'], 'tips' => 'Hook binnen 3 sec'],
        'pinterest' => ['label' => 'Pinterest', 'caption_min' => 150, 'caption_max' => 300, 'hashtags_min' => 5, 'hashtags_max' => 10, 'ratios' => ['2:3'], 'tips' => 'Long-tail keywords in beschrijving'],
        'facebook' => ['label' => 'Facebook', 'caption_min' => 50, 'caption_max' => 500, 'hashtags_min' => 2, 'hashtags_max' => 3, 'ratios' => ['1:1', '16:9'], 'tips' => 'Vraag of CTA'],
        'tiktok' => ['label' => 'TikTok', 'caption_min' => 10, 'caption_max' => 80, 'hashtags_min' => 3, 'hashtags_max' => 5, 'ratios' => ['9:16'], 'tips' => 'Hook + trend suggestie'],
    ],
    'image_generation' => [
        'style_presets' => ['minimalist' => 'Minimalistisch interieur', 'lifestyle' => 'Lifestyle', 'flat_lay' => 'Flat lay', 'moody' => 'Moody', 'bright_airy' => 'Bright & airy'],
        'ratios' => ['4:5', '1:1', '9:16', '2:3'],
        'default_strength' => 0.7,
    ],
    'context_builder' => ['max_tokens' => 8000, 'max_products' => 50],
];

<?php

return [
    'sanitizer' => [
        'replace_em_dashes' => true,
        'cliches' => [
            'in de snel veranderende wereld van',
            'het is belangrijk om te onthouden',
            'het is belangrijk om te vermelden',
            'duik in',
            'ontdek de geheimen van',
            'in deze digitale tijd',
            'laten we eens kijken naar',
            'welnu',
            'in dit artikel gaan we',
            'of het nu gaat om',
            'uiteindelijk is het aan jou',
            'de mogelijkheden zijn eindeloos',
            'revolutionair',
            'baanbrekend',
            'next-level',
            'game-changer',
            'in een notendop',
            'last but not least',
            'kortom',
            'al met al',
            'kort samengevat',
            'de waarheid is',
            'of je nu … of …',
            'aan het einde van de dag',
            'in een wereld waar',
            'de realiteit is dat',
            'één ding is zeker',
            'het gaat niet alleen om',
            'meer dan ooit tevoren',
            'moet je gewoon',
        ],
    ],

    'matcher' => [
        'text_high_threshold' => 0.75,
        'text_candidate_threshold' => 0.40,
        'embedding_high_threshold' => 0.90,
        'embedding_candidate_threshold' => 0.80,
        'ai_confirm_threshold' => 0.70,
        'top_n_ai_candidates' => 5,
    ],

    'embeddings' => [
        'enabled' => true,
        'provider' => null,
        'model' => 'text-embedding-3-small',
    ],

    'stopwords' => [
        'nl' => ['de', 'het', 'een', 'en', 'of', 'maar', 'van', 'voor', 'op', 'aan', 'met', 'bij', 'te', 'in', 'is', 'zijn', 'was', 'waren', 'dat', 'die', 'deze', 'dit', 'er', 'ook', 'dan', 'wel', 'als'],
        'en' => ['the', 'a', 'an', 'and', 'or', 'but', 'of', 'for', 'on', 'at', 'with', 'by', 'to', 'in', 'is', 'are', 'was', 'were', 'that', 'this', 'these', 'those', 'there', 'also', 'than', 'as'],
    ],

    'internal_links' => [
        'max_per_generation' => 3,
        'candidate_pool_size' => 20,
    ],
];

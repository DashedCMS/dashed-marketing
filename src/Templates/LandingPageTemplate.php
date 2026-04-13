<?php

namespace Dashed\DashedMarketing\Templates;

use Dashed\DashedMarketing\Contracts\ContentTemplate;
use Illuminate\Database\Eloquent\Model;

class LandingPageTemplate implements ContentTemplate
{
    public const TARGET = 'Dashed\\DashedCore\\Models\\Page';

    public function contentType(): string
    {
        return 'landing_page';
    }

    public function blocks(): array
    {
        return [
            ['key' => 'header',            'type' => 'header',             'required' => true],
            ['key' => 'intro_content',     'type' => 'content',            'required' => true],
            ['key' => 'usps',              'type' => 'usps-with-icon',     'required' => true],
            ['key' => 'feature_1',         'type' => 'content-with-image', 'required' => true],
            ['key' => 'feature_2',         'type' => 'content-with-image', 'required' => true],
            ['key' => 'faq_content',       'type' => 'content',            'required' => true],
        ];
    }

    public function optionalBlocks(): array
    {
        return [
            ['key' => 'feature_3', 'type' => 'content-with-image'],
            ['key' => 'feature_4', 'type' => 'content-with-image'],
        ];
    }

    public function promptContext(): string
    {
        return <<<'TXT'
Je schrijft een landingspagina voor een Nederlandstalige commerciële site.
Vaste opbouw:
1. header (hero): korte krachtige titel en subtitel, één CTA zin
2. intro_content: 2-3 korte paragrafen die de belofte uitleggen
3. usps: 3-4 USPs met iconen (korte titel + 1 zin uitleg per USP)
4. feature_1 en feature_2: tekst + image prompt, afwisselend focus
5. faq_content: 4-6 veelgestelde vragen met korte antwoorden
Je mag feature_3 en feature_4 toevoegen als het onderwerp het rechtvaardigt.
Geen em-dashes. Actieve zinnen.
TXT;
    }

    public function outputSchema(): array
    {
        return [
            'blocks' => [
                'type' => 'array',
                'items' => [
                    'key' => 'string',
                    'type' => 'string',
                    'data' => 'object',
                ],
            ],
        ];
    }

    public function canTarget(string $modelClass): bool
    {
        return $modelClass === self::TARGET;
    }

    public function applyTo(Model $subject, array $content): void
    {
        $blocks = array_map(
            fn ($block) => ['type' => $block['type'], 'data' => $block['data'] ?? []],
            $content['blocks'] ?? [],
        );

        if (method_exists($subject, 'customBlocks')) {
            $subject->customBlocks()->updateOrCreate(
                ['blockable_type' => $subject::class, 'blockable_id' => $subject->id],
                ['blocks' => $blocks],
            );
        }
    }
}

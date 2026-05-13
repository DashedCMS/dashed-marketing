<?php

namespace Dashed\DashedMarketing\Templates;

use Illuminate\Database\Eloquent\Model;
use Dashed\DashedMarketing\Contracts\ContentTemplate;

class BlogArticleTemplate implements ContentTemplate
{
    public const TARGET = 'Dashed\\DashedArticles\\Models\\Article';

    public function contentType(): string
    {
        return 'blog';
    }

    public function blocks(): array
    {
        return [
            ['key' => 'intro',      'type' => 'intro',   'required' => true],
            ['key' => 'section_1',  'type' => 'h2',      'required' => true],
            ['key' => 'section_2',  'type' => 'h2',      'required' => true],
            ['key' => 'section_3',  'type' => 'h2',      'required' => true],
            ['key' => 'conclusion', 'type' => 'outro',   'required' => true],
        ];
    }

    public function optionalBlocks(): array
    {
        return [
            ['key' => 'section_4', 'type' => 'h2'],
            ['key' => 'section_5', 'type' => 'h2'],
            ['key' => 'section_6', 'type' => 'h2'],
        ];
    }

    public function promptContext(): string
    {
        return <<<'TXT'
Je schrijft een blogartikel voor een Nederlandstalige website.
Structuur: korte intro (geen heading), 3-6 H2 secties met paragrafen en optionele bullets, afsluiting.
Elke sectie moet onafhankelijk leesbaar zijn en natuurlijk aansluiten op het onderwerp.
Vermijd herhaling tussen secties. Schrijf concreet, actief, in "je"-vorm.
TXT;
    }

    public function outputSchema(): array
    {
        return [
            'h2_sections' => [
                'type' => 'array',
                'items' => [
                    'id' => 'string',
                    'heading' => 'string',
                    'body' => 'string',
                    'order' => 'integer',
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
        $blocks = [];
        foreach ($content['h2_sections'] ?? [] as $section) {
            if (($section['id'] ?? null) === 'intro') {
                $blocks[] = ['type' => 'content', 'data' => ['content' => $section['body']]];

                continue;
            }
            $blocks[] = ['type' => 'header', 'data' => ['title' => $section['heading']]];
            $blocks[] = ['type' => 'content', 'data' => ['content' => $section['body']]];
        }

        if (method_exists($subject, 'customBlocks')) {
            $subject->customBlocks()->updateOrCreate(
                ['blockable_type' => $subject::class, 'blockable_id' => $subject->id],
                ['blocks' => $blocks],
            );
        }
    }
}

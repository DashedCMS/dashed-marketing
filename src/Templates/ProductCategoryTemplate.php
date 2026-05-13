<?php

namespace Dashed\DashedMarketing\Templates;

use Illuminate\Database\Eloquent\Model;
use Dashed\DashedMarketing\Contracts\ContentTemplate;

class ProductCategoryTemplate implements ContentTemplate
{
    public const TARGET = 'Dashed\\DashedEcommerceCore\\Models\\ProductCategory';

    public function contentType(): string
    {
        return 'category';
    }

    public function blocks(): array
    {
        return [
            ['key' => 'category_intro',    'type' => 'content', 'required' => true],
            ['key' => 'category_seo_text', 'type' => 'content', 'required' => true],
        ];
    }

    public function optionalBlocks(): array
    {
        return [
            ['key' => 'faq_content',       'type' => 'content'],
            ['key' => 'buying_guide',      'type' => 'content'],
        ];
    }

    public function promptContext(): string
    {
        return <<<'TXT'
Je schrijft SEO-tekst voor een productcategoriepagina.
- category_intro: 2-3 zinnen die bovenaan de productlijst staan, helder en uitnodigend
- category_seo_text: langere tekst die onderaan de pagina staat, gestructureerd met H2/H3, minimaal 300 woorden, concrete voordelen en gebruikscases
Optioneel: faq_content (4-6 vragen) en buying_guide (hoe kies je een product uit deze categorie).
Geen em-dashes. Schrijf voor klanten die op zoek zijn, niet voor zoekmachines.
TXT;
    }

    public function outputSchema(): array
    {
        return [
            'blocks' => [
                'type' => 'array',
                'items' => ['key' => 'string', 'type' => 'string', 'data' => 'object'],
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

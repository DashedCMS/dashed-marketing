<?php

namespace Dashed\DashedMarketing\Templates;

use Dashed\DashedMarketing\Contracts\ContentTemplate;
use Illuminate\Database\Eloquent\Model;

class ProductTemplate implements ContentTemplate
{
    public const TARGET = 'Dashed\\DashedEcommerceCore\\Models\\Product';

    public function contentType(): string
    {
        return 'product';
    }

    public function blocks(): array
    {
        return [
            ['key' => 'short_description', 'type' => 'field', 'required' => true],
            ['key' => 'long_description',  'type' => 'field', 'required' => true],
            ['key' => 'usps',              'type' => 'field', 'required' => true],
            ['key' => 'meta_title',        'type' => 'field', 'required' => true],
            ['key' => 'meta_description',  'type' => 'field', 'required' => true],
        ];
    }

    public function optionalBlocks(): array
    {
        return [];
    }

    public function promptContext(): string
    {
        return <<<'TXT'
Je verbetert teksten van een bestaand product. Geen nieuw product ontwerpen.
- short_description: 1-2 zinnen, punt waarom klant dit wil
- long_description: uitgebreide productuitleg met H2/H3, materialen, gebruik, onderhoud
- usps: 3-5 korte bullets met het belangrijkste verkoopargument per bullet
- meta_title: max 60 tekens, bevat hoofdkeyword
- meta_description: 140-160 tekens, CTR-waardig
Geen em-dashes. Geen loze superlatieven.
TXT;
    }

    public function outputSchema(): array
    {
        return [
            'short_description' => 'string',
            'long_description' => 'string',
            'usps' => 'array',
            'meta_title' => 'string',
            'meta_description' => 'string',
        ];
    }

    public function canTarget(string $modelClass): bool
    {
        return $modelClass === self::TARGET;
    }

    public function applyTo(Model $subject, array $content): void
    {
        $subject->update(array_intersect_key($content, array_flip([
            'short_description',
            'long_description',
            'usps',
            'meta_title',
            'meta_description',
        ])));
    }
}

<?php

namespace Dashed\DashedMarketing\Services\Prompts;

class SeoAuditPromptBuilder
{
    /**
     * @param  array<string, mixed>  $context
     */
    public static function pageAnalysis(array $context): string
    {
        $subject = $context['subject'];
        $locale = $context['locale'];
        $blocks = json_encode($context['current_blocks'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $meta = json_encode($context['current_meta'], JSON_UNESCAPED_UNICODE);

        return <<<TXT
Analyseer deze {$subject['type']} pagina "{$subject['name']}" (locale: {$locale}, URL: {$subject['url']}) op de huidige SEO-staat.

Content blokken (JSON):
{$blocks}

Huidige meta: {$meta}

Regels:
- Geen em-dashes, geen emoji, geen AI-clichés.
- headings_structure: platte array van {level:1..6, text}. Behoud volgorde.
- content_length: totaal aantal woorden over alle zichtbare tekst in blokken.
- keyword_density: top 5 meest voorkomende content-woorden >=3 letters, als {"woord": percentage}.
- alt_text_coverage: {total: int, with_alt: int, missing: int}. Als er geen images zijn, alle drie 0.
- readability_score: 0-100, hoger = beter leesbaar.
- notes: korte Nederlandse observatie 1-3 zinnen.

Retourneer JSON: {"summary": "...", "headings_structure": [...], "content_length": 0, "keyword_density": {}, "alt_text_coverage": {}, "readability_score": 0, "notes": "..."}
TXT;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function keywords(array $context): string
    {
        $subject = $context['subject'];
        $instruction = $context['user_instruction'] ? "Instructie: {$context['user_instruction']}\n\n" : '';
        $brand = $context['brand'];
        $seeded = array_values(array_unique(array_map('trim', (array) ($context['seeded_keywords'] ?? []))));
        $seededJson = json_encode($seeded, JSON_UNESCAPED_UNICODE);

        return <<<TXT
{$instruction}Vul aan op de curated keyword-research voor "{$subject['name']}" (locale: {$context['locale']}).

Merk-context:
{$brand}

Al beschikbare keywords uit de zoekwoord-research (niet herhalen):
{$seededJson}

Regels:
- Geef UITSLUITEND LSI/semantic en gap keywords. Primary/secondary/longtail komen uit de research, niet uit AI.
- LSI: semantisch verwant aan de bestaande keywords; voeg 3-5 toe.
- Gap: relevante keywords die de research mist; voeg 2-3 toe.
- Vermijd duplicates van de lijst hierboven, ook niet in vervoegingen/meervouden die hetzelfde bedoelen.
- intent per keyword: informational | commercial | transactional | navigational.
- volume_indication: high | medium | low (qualitatief, geen verzonnen cijfers).
- priority: high | medium | low.
- Geen em-dashes, geen AI-clichés.

Retourneer JSON: {"summary": "...", "suggestions": [{"keyword": "...", "type": "lsi|gap", "intent": "...", "volume_indication": "...", "priority": "...", "notes": "..."}]}
TXT;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function meta(array $context): string
    {
        $current = json_encode($context['current_meta'], JSON_UNESCAPED_UNICODE);
        $instruction = $context['user_instruction'] ? "Instructie: {$context['user_instruction']}\n\n" : '';
        $brand = $context['brand'];
        $subject = $context['subject'];

        return <<<TXT
{$instruction}Stel meta-verbeteringen voor voor "{$subject['name']}" (locale: {$context['locale']}).

Huidige waarden:
{$current}

Merk-context:
{$brand}

Regels (hard):
- Veld-opties: name, slug, excerpt, meta_title, meta_description.
- meta_title: 50-60 tekens, bevat primair keyword.
- meta_description: 140-160 tekens, eindigt met subtiele CTA.
- slug: kort, lowercase, alleen voorstellen als de huidige echt slechter is.
- Als een veld al goed is, laat het WEG uit suggestions.
- Geen em-dashes, geen AI-clichés.

Retourneer JSON: {"summary": "...", "suggestions": [{"field": "meta_title", "suggested_value": "...", "reason": "...", "priority": "high|medium|low"}]}
TXT;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function blocks(array $context): string
    {
        $blocks = json_encode($context['current_blocks'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $whitelist = json_encode($context['block_whitelist'], JSON_UNESCAPED_UNICODE);
        $instruction = $context['user_instruction'] ? "Instructie: {$context['user_instruction']}\n\n" : '';
        $brand = $context['brand'];
        $subject = $context['subject'];

        return <<<TXT
{$instruction}Herschrijf content-blokken voor betere SEO op "{$subject['name']}".

Huidige blokken (met index, type, data):
{$blocks}

Whitelist per block-type, alleen deze field_keys mogen voorgesteld worden:
{$whitelist}

Merk-context:
{$brand}

Regels (hard):
- Per suggestion: {block_index, block_type, field_key, suggested_value, reason, priority}. field_key moet in de whitelist staan voor dat block-type.
- Laat blokken die al goed zijn WEG.
- is_new_block=true is toegestaan voor ontbrekende blokken: block_index=null, block_type uit de whitelist, suggested_value is een JSON-string met de volledige `data` van het nieuwe blok (toggles in_container/top_margin/bottom_margin op true).
- Behoud HTML in content-velden (p, ul, ol, li, strong, em, a). Geen headings in content-velden (h1-h6).
- Geen em-dashes, geen emoji, geen AI-clichés.

Retourneer JSON: {"summary": "...", "suggestions": [{"block_index": 0, "block_type": "content", "field_key": "content", "suggested_value": "<p>...</p>", "reason": "...", "priority": "high|medium|low", "is_new_block": false}]}
TXT;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function faqs(array $context): string
    {
        $existing = json_encode($context['existing_faqs'] ?? [], JSON_UNESCAPED_UNICODE);
        $instruction = $context['user_instruction'] ? "Instructie: {$context['user_instruction']}\n\n" : '';
        $brand = $context['brand'];
        $subject = $context['subject'];

        return <<<TXT
{$instruction}Bedenk 5-10 FAQ items voor "{$subject['name']}" (locale: {$context['locale']}).

Bestaande FAQs om te vermijden:
{$existing}

Merk-context:
{$brand}

Regels:
- Elke vraag is een realistische "People Also Ask" vraag over dit onderwerp.
- Antwoord: beknopt, concreet, 1-3 zinnen, geen verkooppraat.
- target_keyword: welk keyword dit antwoord versterkt.
- Geen duplicates van bestaande FAQs.
- Geen em-dashes, geen AI-clichés.

Retourneer JSON: {"summary": "...", "suggestions": [{"question": "...", "answer": "...", "target_keyword": "...", "priority": "high|medium|low"}]}
TXT;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function structuredData(array $context): string
    {
        $subject = $context['subject'];
        $blocks = json_encode($context['current_blocks'], JSON_UNESCAPED_UNICODE);

        return <<<TXT
Stel JSON-LD structured data voor voor "{$subject['name']}" (type: {$subject['type']}, URL: {$subject['url']}).

Content-context voor semantiek:
{$blocks}

Regels (hard):
- Alleen schema types die relevant zijn voor dit soort content.
- json_ld MOET valide parseable JSON zijn, inclusief @context en @type.
- Gebruik de URL {$subject['url']} voor @id/url velden.
- Geen placeholders of "..." in de output.
- Priorities: FAQPage/Product/Article=high, BreadcrumbList=medium, Organization=low.

Retourneer JSON: {"summary": "...", "suggestions": [{"schema_type": "FAQPage", "json_ld": "{\"@context\":\"https://schema.org\",...}", "reason": "...", "priority": "high|medium|low"}]}
TXT;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function internalLinks(array $context): string
    {
        $subject = $context['subject'];
        $blocks = json_encode($context['current_blocks'], JSON_UNESCAPED_UNICODE);
        $pool = collect($context['route_pool'])->take(80)->map(fn ($c, $i) => sprintf(
            '%d | %s | %s | %s',
            $i,
            $c['type'] ?? '',
            $c['title'] ?? '',
            $c['url'] ?? ''
        ))->implode("\n");

        return <<<TXT
Stel interne links voor op "{$subject['name']}".

Content:
{$blocks}

Pool van beschikbare target pagina's (index | type | titel | url):
{$pool}

Regels (hard):
- target_url moet EXACT overeenkomen met een url uit de pool.
- anchor_text: natuurlijke, lezers-gerichte ankertekst.
- context_description: in 1-2 zinnen waar in de content de link past.
- Geen links naar het onderwerp zelf.
- 3-8 suggesties, alleen als ze echt thematisch passen.

Retourneer JSON: {"summary": "...", "suggestions": [{"anchor_text": "...", "target_url": "/...", "target_subject_type": null, "target_subject_id": null, "context_description": "...", "reason": "...", "priority": "high|medium|low"}]}
TXT;
    }
}

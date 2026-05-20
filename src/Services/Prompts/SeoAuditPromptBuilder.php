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
        $blocks = json_encode($context['current_blocks'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $meta = json_encode($context['current_meta'] ?? [], JSON_UNESCAPED_UNICODE);

        return <<<TXT
{$instruction}Vul aan op de curated keyword-research voor "{$subject['name']}" (locale: {$context['locale']}).

Merk-context:
{$brand}

Huidige meta van deze pagina:
{$meta}

Content-blokken van deze pagina (JSON, gebruik ALLEEN als ankerpunt voor onderwerp en woordkeus van suggesties):
{$blocks}

Al beschikbare keywords uit de zoekwoord-research (niet herhalen):
{$seededJson}

Regels:
- Geef UITSLUITEND LSI/semantic en gap keywords. Primary/secondary/longtail komen uit de research, niet uit AI.
- Iedere suggestie moet aantoonbaar over het onderwerp van DEZE pagina gaan (zoals zichtbaar in de content/meta hierboven). Niet over het bedrijf in het algemeen of over andere paginas.
- Wees kieskeurig: liever 2 sterke suggesties dan 5 zwakke. Sla LSI of gap helemaal over als je niets sterks kunt bedenken.
- LSI: 1-3 echt semantisch verwante termen die de huidige pagina-content versterken.
- Gap: 0-2 keywords die de pagina mist maar logisch hoort te dekken op basis van zijn onderwerp.
- Maximaal 5 suggesties in totaal.
- Vermijd duplicates van de research-lijst, ook niet in vervoegingen/meervouden of synoniemen die hetzelfde bedoelen.
- intent per keyword: informational | commercial | transactional | navigational.
- volume_indication: high | medium | low (qualitatief, geen verzonnen cijfers).
- priority: high | medium | low (zet alleen high als de keyword zonder twijfel matcht met de pagina-intentie).
- notes: 1 korte zin (max 12 woorden) die uitlegt waarom dit bij DEZE pagina past.
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
     * Outline prompt: ask AI for H1 + short page summary + H2/H3 headings only.
     * Actual per-heading content is generated in a later step after the user
     * reviews (and edits) the outline.
     *
     * @param  array<string, mixed>  $context
     */
    public static function outline(array $context): string
    {
        $subject = $context['subject'];
        $instruction = $context['user_instruction'] ? "Instructie: {$context['user_instruction']}\n\n" : '';
        $brand = $context['brand'];
        $existingBlocks = json_encode($context['current_blocks'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $seededKeywords = array_values(array_unique(array_map('trim', (array) ($context['seeded_keywords'] ?? []))));
        $seededJson = json_encode($seededKeywords, JSON_UNESCAPED_UNICODE);

        return <<<TXT
{$instruction}Ontwerp een content-outline voor "{$subject['name']}" (type: {$subject['type']}, locale: {$context['locale']}).

Merk-context:
{$brand}

Huidige blokken (ter referentie, om doublures te vermijden):
{$existingBlocks}

Relevante keywords voor de pagina:
{$seededJson}

Regels (hard):
- h1: 1 pakkende H1, 50-70 tekens, bevat primair keyword natuurlijk.
- summary: 2-3 zinnen Nederlands die vertellen wat de pagina behandelt. Dit is context voor de volgende content-generatie-stap, geen user-facing tekst.
- headings: array van H2 (en optioneel H3) secties die de pagina logisch opdelen. Minimaal 3, maximaal 8 H2's. H3 alleen waar semantisch nodig.
- GEEN FAQ-heading opnemen ("Veelgestelde vragen", "FAQ", "Frequently Asked Questions", etc.). Veelgestelde vragen worden in een aparte FAQ-stap afgehandeld en krijgen een eigen blok op de pagina; een outline-heading daarvoor leidt tot duplicatie.
- Geen em-dashes, geen emoji, geen AI-clichés.
- Geen content nu. Alleen de outline.

Retourneer JSON:
{"h1": "...", "summary": "...", "headings": [{"level": 2, "text": "..."}, {"level": 3, "text": "..."}]}
TXT;
    }

    /**
     * Per-heading content prompt, called after the user confirms (and possibly
     * edits) the outline. Produces the body HTML for a single section.
     *
     * @param  array<string, mixed>  $context
     */
    /**
     * @param  array<string, mixed>  $context
     * @param  array<int, string>  $avoidPhrases
     */
    public static function outlineContent(array $context, string $heading, int $headingLevel, string $h1, string $summary, array $avoidPhrases = []): string
    {
        $instruction = $context['user_instruction'] ? "Instructie: {$context['user_instruction']}\n\n" : '';
        $brand = $context['brand'];
        $seededKeywords = array_values(array_unique(array_map('trim', (array) ($context['seeded_keywords'] ?? []))));
        $seededJson = json_encode($seededKeywords, JSON_UNESCAPED_UNICODE);

        $avoidBlock = '';
        if ($avoidPhrases !== []) {
            $avoidJson = json_encode(array_values(array_filter($avoidPhrases)), JSON_UNESCAPED_UNICODE);
            $avoidBlock = "\n\nDeze zinnen/inhoud staan al in andere secties van dezelfde pagina, NIET herhalen of parafraseren:\n{$avoidJson}\n";
        }

        return <<<TXT
{$instruction}Schrijf body HTML voor één sectie van een pagina.

Pagina-context:
- H1: {$h1}
- Samenvatting: {$summary}

Sectie:
- Heading (H{$headingLevel}): {$heading}

Merk-context:
{$brand}

Keywords om natuurlijk in te verwerken:
{$seededJson}{$avoidBlock}

Regels (hard):
- Retourneer HTML body in veld "body".
- Alleen deze tags: p, ul, ol, li, strong, em, a.
- GEEN heading tags (h1-h6), GEEN script/style/img.
- 2-5 alinea's, varierend, actieve vorm, "je"-vorm waar passend.
- Geen em-dashes, geen emoji, geen AI-clichés.
- Interne links mag, maar alleen als de keyword-anchor natuurlijk past.

Retourneer JSON: {"body": "<p>...</p>"}
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

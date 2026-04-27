<?php

namespace Dashed\DashedMarketing\Services;

use Dashed\DashedAi\Exceptions\AiException;
use Dashed\DashedAi\Exceptions\AiRateLimitException;
use Dashed\DashedAi\Facades\Ai;
use Dashed\DashedCore\Models\Customsetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Generates a production-grade English image prompt for an image-to-image
 * model (fal nano-banana / flux-kontext) given:
 *   - a real product photo (Claude vision sees the product directly)
 *   - a theme / occasion (e.g. Koningsdag, Kerst, Lente)
 *   - optional brand voice (story + writing style passed dynamically)
 *
 * The output is ONLY the prompt itself, no preamble or markdown, ending with
 * an explicit "do not alter the product" lock so the downstream image-edit
 * model preserves the input pixel-faithfully.
 */
class ProductPromptGenerator
{
    public const SYSTEM_VERSION = 'v1.1.2026-04-27';

    public const LOCK_CLAUSE = 'the product itself must remain identical to the reference image, do not alter its shape, color, texture, materials, finish, logos, labels or proportions.';

    public const DEFAULT_MAX_TOKENS = 1000;

    public const DEFAULT_TEMPERATURE = 0.7;

    public const DEFAULT_MODEL = 'claude-sonnet-4-6';

    /**
     * Theme → concrete iconography hint table. Used to pre-supply specifics
     * so a one-word theme expands into a richly grounded scene without
     * depending on Claude's holiday knowledge.
     *
     * @var array<string, string>
     */
    public const THEME_HINTS = [
        'koningsdag' => 'King\'s Day in the Netherlands. Concrete iconography: small orange tulips, a tiny Dutch flag, orange streamers, orange crown decorations, blue-and-white Delft accents, optional Amsterdam canal house exterior or brown wooden boat with orange flags.',
        'kerst' => 'Dutch Christmas / Kerst. Concrete iconography: pine sprigs, red berries, beeswax candles, linen napkins, soft snow on a windowsill, eucalyptus, a single ornament, warm low-key lighting.',
        'sinterklaas' => 'Dutch Sinterklaas. Concrete iconography: pepernoten, kruidnoten, a small jute sack, a wooden shoe with hay, a few orange and white chocolate letters, soft warm interior light.',
        'lente' => 'Spring / lente. Concrete iconography: white tulips, daffodils, a sprig of cherry blossom in a small bud vase, soft pastel linen, fresh green leaves, bright morning daylight.',
        'zomer' => 'Summer / zomer. Concrete iconography: sunlit linen, a cold drink with condensation, woven straw, lemon slices, dried grasses, warm late-afternoon sun, golden hour glow.',
        'herfst' => 'Autumn / herfst. Concrete iconography: dried oak leaves, hazelnuts, a few small pumpkins, knitted wool throw, beeswax candle, soft overcast daylight, earthy palette.',
        'moederdag' => 'Mother\'s Day. Concrete iconography: a small bouquet of peonies or ranunculus, linen ribbon, a handwritten card, a porcelain cup of tea, soft window light.',
        'vaderdag' => 'Father\'s Day. Concrete iconography: an aged leather wallet, an espresso cup, a folded newspaper, walnut wood surface, warm tungsten side-light.',
        'valentijn' => 'Valentine\'s Day. Concrete iconography: dried red roses, a single beeswax taper candle, a folded handwritten note, dark linen, low warm side-light, intimate mood.',
        'pasen' => 'Easter / Pasen. Concrete iconography: a small willow branch with catkins, a few pastel-painted eggs in a linen-lined bowl, fresh tulips, soft morning daylight.',
        'halloween' => 'Halloween (subtle, premium take). Concrete iconography: a few small heirloom pumpkins, dried wheat, beeswax candles, dried oak leaves, deep amber low-key lighting.',
    ];

    /**
     * Generate an image prompt for a product photo + theme.
     *
     * Options:
     *   - model: string (default DEFAULT_MODEL)
     *   - max_tokens: int (default DEFAULT_MAX_TOKENS)
     *   - temperature: float (default DEFAULT_TEMPERATURE)
     *   - brand_name: ?string — used to address the brand by name
     *   - brand_story: ?string — short paragraph about the brand
     *   - writing_style: ?string — tone/style description
     *   - product_name: ?string — explicit product name (e.g. "Lovora Family Figurine")
     *   - product_context: ?string — structured product info (material, dimensions, finish, USP, design language) so Claude can anchor the prompt in the actual product
     *   - cache_ttl: int seconds (default 86400, 0 disables caching)
     *   - extra_instructions: ?string — free-form user instructions
     *
     * @throws RuntimeException when the file is unreadable
     */
    public function generate(string $imagePath, string $theme, array $options = []): string
    {
        if (! is_file($imagePath) || ! is_readable($imagePath)) {
            throw new RuntimeException("Product image not readable: {$imagePath}");
        }

        $contents = file_get_contents($imagePath);
        if ($contents === false) {
            throw new RuntimeException("Failed to read product image: {$imagePath}");
        }

        $mimeType = $this->detectMimeType($imagePath, $contents);
        $imageData = base64_encode($contents);
        $imageHash = md5($contents);

        $themeKey = $this->normalizeThemeKey($theme);
        $themeHint = self::THEME_HINTS[$themeKey] ?? null;

        $brandName = trim((string) ($options['brand_name'] ?? Customsetting::get('site_name', null, '') ?: ''));
        $brandStory = trim((string) ($options['brand_story'] ?? Customsetting::get('ai_brand_story', null, '') ?: ''));
        $writingStyle = trim((string) ($options['writing_style'] ?? Customsetting::get('ai_writing_style', null, '') ?: ''));
        $productName = trim((string) ($options['product_name'] ?? ''));
        $productContext = trim((string) ($options['product_context'] ?? ''));
        $extra = trim((string) ($options['extra_instructions'] ?? ''));

        $model = (string) ($options['model'] ?? self::DEFAULT_MODEL);
        $maxTokens = (int) ($options['max_tokens'] ?? self::DEFAULT_MAX_TOKENS);
        $temperature = (float) ($options['temperature'] ?? self::DEFAULT_TEMPERATURE);

        $cacheTtl = (int) ($options['cache_ttl'] ?? 86400);
        $cacheKey = 'product_prompt:'.md5(implode('|', [
            self::SYSTEM_VERSION,
            $imageHash,
            $themeKey,
            $model,
            $brandName,
            $brandStory,
            $writingStyle,
            $productName,
            $productContext,
            $extra,
        ]));

        if ($cacheTtl > 0 && ($cached = Cache::get($cacheKey))) {
            return $cached;
        }

        $systemPrompt = $this->buildSystem($brandName, $brandStory, $writingStyle);
        $userPrompt = $this->buildUser($theme, $themeHint, $productName, $productContext, $extra);

        try {
            $raw = Ai::vision($userPrompt, $imageData, $mimeType, [
                'system' => $systemPrompt,
                'disable_brand_rules' => true,
                'model' => $model,
                'max_tokens' => $maxTokens,
                'temperature' => $temperature,
            ]);
        } catch (AiRateLimitException $e) {
            Log::warning('[product-prompt] rate limit', ['theme' => $theme, 'error' => $e->getMessage()]);
            throw $e;
        } catch (AiException|ConnectionException $e) {
            Log::error('[product-prompt] ai error', ['theme' => $theme, 'error' => $e->getMessage()]);
            throw $e;
        }

        $prompt = $this->cleanResponse($raw);

        if ($prompt === '') {
            Log::warning('[product-prompt] empty response', [
                'theme' => $theme,
                'model' => $model,
                'image_hash' => $imageHash,
            ]);
            throw new RuntimeException('AI returned an empty product prompt.');
        }

        Log::info('[product-prompt] generated', [
            'theme' => $theme,
            'theme_known' => $themeHint !== null,
            'model' => $model,
            'image_hash' => $imageHash,
            'prompt_length' => strlen($prompt),
        ]);

        if ($cacheTtl > 0) {
            Cache::put($cacheKey, $prompt, $cacheTtl);
        }

        return $prompt;
    }

    /**
     * Available theme keys exposed for UI dropdowns / docs.
     *
     * @return array<int, string>
     */
    public static function knownThemes(): array
    {
        return array_keys(self::THEME_HINTS);
    }

    private function buildSystem(string $brandName, string $brandStory, string $writingStyle): string
    {
        $brandLine = $brandName !== ''
            ? "You write image prompts for the brand **{$brandName}**."
            : 'You write image prompts for a premium consumer brand.';

        $brandStorySection = $brandStory !== '' ? "\n\nBrand story:\n{$brandStory}" : '';
        $writingStyleSection = $writingStyle !== '' ? "\n\nBrand voice / writing style:\n{$writingStyle}" : '';

        return <<<SYS
        You are a senior product photography prompt engineer. {$brandLine} The prompts you write feed an image-to-image model (fal nano-banana / flux-kontext) that EDITS a reference product photo into a styled lifestyle shot. The model is exceptionally faithful to the input image when you instruct it correctly.{$brandStorySection}{$writingStyleSection}

        ## Product hero framing (CRITICAL)
        The PRODUCT is the protagonist. Roughly 60-70% of the descriptive weight of the prompt MUST be about the product itself, not about the scene around it. Devote vivid, specific language to:
        - The product's silhouette and presence in frame (the unambiguous focal point, sized to dominate).
        - Its surface qualities — finish, micro-textures, layer lines, sheen, matte vs satin — as the lens at the chosen focal length would actually resolve them at this distance.
        - How light falls on it: the direction of the highlight, where the shadow turns, what the rim of the form looks like against the background.
        - The way its volume reads — depth, weight, contour.
        - If product info is supplied below, weave at least 3 specific phrases from it (brand name, material, finish, design language, intended use, dimensions) directly into the description so the prompt is unmistakably about THIS product, not a generic version of it.
        Props and setting are SUPPORTING. They place the product in context but never compete with it for attention. Describe them briefly and only after the product has been fully described.

        ## Output rules (NON-NEGOTIABLE)
        - Output ONLY the prompt. No preamble, no headers, no markdown, no quotes around the prompt, no "Here is..." opener, no trailing notes.
        - Write in ENGLISH.
        - Aim for 110-180 words, dense and concrete. The prompt should feel like 4-6 lines of vivid description, not a list.
        - End with this EXACT sentence as the final clause: "the product itself must remain identical to the reference image, do not alter its shape, color, texture, materials, finish, logos, labels or proportions."

        ## Required components, roughly in this order
        1. **Style declaration + product hero opener** — open with a concrete photographic style AND name the product as the unambiguous hero of the shot. Examples: "Photorealistic product photo with the [Product] as the unambiguous hero, ...", "Cinematic lifestyle shot centered on the [Product], ...". Match the brand vibe.
        2. **Product description** — what's in frame, drawn from BOTH what you see in the reference image AND the supplied product info. Be specific about material, finish, design language, and how the lens resolves its surface at this distance. Do not invent visible details not in the image; do borrow descriptive language from the product info.
        3. **Setting** — a real, specific place. Never just "background". Examples: "weathered oak ledge outside a typical Dutch canal house", "linen-covered marble countertop in a sunlit kitchen", "chalk-painted alcove with a single wooden shelf".
        4. **Props (3-5)** — concrete props that reinforce the theme, kept supporting. Use the iconography hint in the user message. Never write vague terms like "festive decorations" or "subtle accents".
        5. **Lighting** — a real time-of-day or quality, then describe how that light interacts with the product specifically (which side catches the highlight, where the shadow falls).
        6. **Camera / optics** — lens (50mm, 85mm macro, 35mm wide), depth of field, framing (close-up, three-quarter angle, eye-level, overhead flat-lay). Explain what the choice does for the product (e.g. "85mm flattens the silhouette and resolves the matte print layers").
        7. **Color palette & mood** — 2-4 specific colors plus a one-line mood.
        8. **Lock clause** — the exact final sentence above.

        ## Negative instructions (DO NOT)
        - Do NOT make the product feel incidental, decorative or "one of many things on the table". It is the hero.
        - Do not include people, hands, faces or body parts.
        - Do not place reflective surfaces directly in front of or wrapping around the product (mirror, chrome, glossy black) — they would warp the product silhouette.
        - Do not request extreme camera angles (fisheye, tilt-shift, top-down on a vertical product) that change the product's apparent shape.
        - Do not put text, logos or watermarks anywhere in the scene unless explicitly instructed.
        - Do not describe the product's color/shape/texture in a way that conflicts with the reference image.
        - Do not use empty filler words: "stylish", "elegant", "modern aesthetic", "vibe", "stunning", "perfect" — replace with concrete visuals.
        - Do not give the props more descriptive weight than the product. If you wrote 3 sentences about props and 1 about the product, rewrite.

        ## Example A (figurine product, Koningsdag, with product info)
        Photorealistic product photo with the Lovora Family Figurine set as the unambiguous hero, filling roughly 60% of the frame. Three matte cream PLA 3D-printed family groupings — a pair, a trio, and a quartet — sit in a loose triangular arrangement, each smooth silhouette catching cool morning side-light from the left so the layered print striations resolve as soft horizontal banding, and the cast shadow gently describes the curve of each figure's shoulders and back. The cream surface reads quiet and sculptural against a warm-white linen tabletop. The setting is the windowsill of a typical Amsterdam canal house, blurred sash window and a sliver of brick visible behind. Supporting props: three small orange tulips laid casually beside the figurines, a thin orange paper streamer trailing across the wood, a tiny Dutch flag tucked between the trio and the quartet. Soft natural daylight with a faint golden cast. Shot on an 85mm lens at f/2.0, three-quarter angle slightly above eye level, shallow depth of field that holds the figurines crisp while everything else falls into a calm bokeh. Palette: warm cream, burnt orange, weathered cedar, canal-water grey-blue. Cozy, premium Dutch lifestyle mood. the product itself must remain identical to the reference image, do not alter its shape, color, texture, materials, finish, logos, labels or proportions.

        ## Example B (sculptural ceramic vase, Kerst)
        Cinematic lifestyle shot centered on the matte stoneware vase as the unambiguous hero, its tall sculptural silhouette taking the upper-left third of the frame. Warm tungsten side-light from camera-right grazes its flank, drawing out the slow ridges of the throwing marks and the soft satin finish where the glaze turns matte. The base is anchored on a linen-draped oak sideboard, the linen weave just sharp enough at this lens to read as fabric. Behind the vase, a softly defocused chalk-painted wall and the faint gold glow of two slim beeswax tapers placed slightly behind. Supporting props kept quiet: a sprig of pine with red winter berries leaning against the base, a folded ivory napkin, a single matte ornament half in shadow. Mid-evening tungsten light, candle glow reflected on the linen. Shot on an 85mm macro at f/2.8, eye-level framing, shallow depth of field. Palette: deep forest green, ivory, warm beeswax amber, soft graphite. Calm, intimate, quietly festive mood. the product itself must remain identical to the reference image, do not alter its shape, color, texture, materials, finish, logos, labels or proportions.
        SYS;
    }

    private function buildUser(string $theme, ?string $themeHint, string $productName, string $productContext, string $extra): string
    {
        $themeBlock = $themeHint
            ? "Theme: **{$theme}**\nIconography to draw from (use specifics, not the whole list — pick what fits the product): {$themeHint}"
            : "Theme: **{$theme}**\n(No pre-supplied iconography — invent specific, concrete props yourself, never write vague placeholders.)";

        $productLines = [];
        if ($productName !== '') {
            $productLines[] = "Product name: {$productName}";
        }
        if ($productContext !== '') {
            $productLines[] = "Product info (use this to anchor the prompt — weave at least 3 specific phrases from it into the description):\n{$productContext}";
        }
        $productBlock = empty($productLines)
            ? "Product info: (none supplied — describe ONLY what you can see in the reference image, and keep the description product-anchored, not scene-anchored.)"
            : implode("\n\n", $productLines);

        $extraBlock = $extra !== ''
            ? "\n\nExtra art-direction from the user (must be honoured):\n{$extra}"
            : '';

        return <<<USR
        Look at the attached product photo. Generate ONE production-grade English image prompt that styles this exact product into a lifestyle scene for the theme below, following every rule from the system prompt — especially the **Product hero framing** rule (60-70% of the prompt's descriptive weight on the product itself).

        {$productBlock}

        {$themeBlock}{$extraBlock}

        Output ONLY the prompt itself, plain text, no preamble.
        USR;
    }

    private function detectMimeType(string $path, string $contents): string
    {
        $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
        if ($finfo) {
            $mime = finfo_buffer($finfo, $contents) ?: null;
            finfo_close($finfo);
            if ($mime && Str::startsWith($mime, 'image/')) {
                return $mime;
            }
        }

        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'image/jpeg',
        };
    }

    private function normalizeThemeKey(string $theme): string
    {
        return Str::of($theme)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', '')->toString();
    }

    /**
     * Strip any preamble / quotes / markdown the model may emit despite the
     * system rules, leaving only the raw prompt. Also enforce the lock clause
     * — the downstream image-edit model needs it to keep the product faithful,
     * and Claude sometimes drops it despite being told it is non-negotiable.
     */
    private function cleanResponse(?string $raw): string
    {
        $text = trim((string) $raw);
        if ($text === '') {
            return '';
        }

        // Drop fenced code blocks.
        if (preg_match('/```(?:\w+)?\s*([\s\S]*?)```/', $text, $m)) {
            $text = trim($m[1]);
        }

        // Drop common preamble lines.
        $text = preg_replace('/^(here(?:\'s| is)|sure[,!]?\s*here(?:\'s| is)|certainly[,!]?|okay[,!]?)[^\n]*\n+/i', '', $text);

        // Strip surrounding quotes.
        $text = trim($text, " \t\n\r\0\x0B\"'`");
        $text = trim($text);

        if ($text === '') {
            return '';
        }

        // Enforce lock clause. We treat the prompt as missing the lock if it
        // doesn't contain "remain identical" — that phrase is the load-bearing
        // bit nano-banana edit needs to keep the product faithful.
        if (! str_contains(strtolower($text), 'remain identical')) {
            $text = rtrim($text, " \t\n\r.").'. '.self::LOCK_CLAUSE;
        }

        return $text;
    }
}

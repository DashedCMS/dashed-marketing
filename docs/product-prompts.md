# Product image prompt generator

Generates a styled lifestyle prompt for an image-to-image model (fal nano-banana / flux-kontext) based on a real product photo plus a theme. The product photo is sent to Claude vision so the resulting prompt actually describes the real product and ends with a hard "do not alter the product" lock clause for the downstream edit model.

## Why this exists

The other social-post generators in this package (`GenerateSocialPostJob`, `RegenerateImagePromptAction`, `GenerateImageAction.generateDistinctImagePrompts`) build prompts from caption text only. Claude has to guess what the product looks like, so the resulting prompt drifts to generic phrases ("subtle festive accents", "Scandinavian aesthetic"). When that prompt is fed to nano-banana edit, the product is preserved by the edit model, but the surrounding scene is unspecific.

`ProductPromptGenerator` closes that gap by sending the product photo itself to Claude, with a strict system prompt that forces concrete photographic specs, a theme iconography table for common Dutch occasions, and a fixed lock sentence at the end so nano-banana keeps the product pixel-faithful.

## Service

```php
use Dashed\DashedMarketing\Services\ProductPromptGenerator;

$prompt = app(ProductPromptGenerator::class)->generate(
    imagePath: '/abs/path/to/product.jpg',
    theme: 'Koningsdag',
    options: [
        'brand_name' => 'Lovora',
        'brand_story' => 'Lovora ontwerpt 3D-geprinte design vazen…',
        'writing_style' => 'premium, minimalistisch, Scandinavisch, warme natuurlijke lighting',
        'extra_instructions' => 'close-up op product, geen mensen',
        'model' => 'claude-sonnet-4-6', // optional override
        'temperature' => 0.7,           // optional
        'max_tokens' => 800,            // optional
        'cache_ttl' => 86400,           // 0 to bypass cache
    ],
);
```

`generate()` returns the raw English prompt as a single string — no preamble, no quotes, no markdown. Throws `RuntimeException` on unreadable image / empty response, and re-throws `AiException` / `AiRateLimitException` from the AI layer.

Brand voice is **dynamic**: `brand_name`, `brand_story`, `writing_style` are passed as options. Defaults fall back to `Customsetting::get('site_name')`, `ai_brand_story`, `ai_writing_style` so the same service works for any consumer (Lovora and others).

Output is cached for 24h by default, keyed on `md5(image bytes + theme + model + brand fields + system version)`. Bumping `ProductPromptGenerator::SYSTEM_VERSION` invalidates all cached prompts.

## Built-in theme iconography

`ProductPromptGenerator::THEME_HINTS` holds a Dutch-flavoured table of pre-supplied scene specifics. When the theme matches one of these keys (case-insensitive, accents/spaces stripped), the iconography is injected into the user message so Claude doesn't have to know the holiday:

- `koningsdag`
- `kerst`
- `sinterklaas`
- `lente`
- `zomer`
- `herfst`
- `moederdag`
- `vaderdag`
- `valentijn`
- `pasen`
- `halloween`

Themes outside the table still work — the system prompt forces Claude to invent specific concrete props rather than vague placeholders.

## Filament integration

`RegenerateImagePromptAction` (single-prompt regen on a `SocialPost`) now opens a modal with **Theme** and **Extra instructions** fields. When the post has a usable local product image, the action calls `ProductPromptGenerator` (vision path); otherwise it falls back to the existing text-only path so posts without an image still work.

The other two callers (`GenerateImageAction.generateDistinctImagePrompts`, `GenerateSocialPostJob`) intentionally still use the text-only path for now — when we're happy with the vision output we can migrate them too.

## Test command

```bash
php artisan dashed:generate-product-prompt {imagePath} {theme}
    [--model=]
    [--brand-name=]
    [--brand-story=]
    [--writing-style=]
    [--instructions=]
    [--no-cache]
```

`imagePath` is resolved against, in order: cwd, `base_path()`, `storage/app/public/{path}`, `storage/app/{path}`, `public/{path}`. So `php artisan dashed:generate-product-prompt social-uploaded/abc.jpg Koningsdag` works directly on uploads.

Output: header with model + theme status (known/unknown), the generated prompt, character count and elapsed time. Use `--no-cache` to force a fresh Claude call when iterating on the system prompt.

## Downstream: feeding nano-banana edit

The generated prompt is designed to be passed straight into the existing `GenerateSocialImageJob` flow:

```php
GenerateSocialImageJob::dispatch(
    post: $post,
    ratio: '4:5',
    stylePreset: 'lifestyle',
    referenceImageUrl: $publicProductImageUrl,
    promptOverride: $prompt, // from ProductPromptGenerator
);
```

When `referenceImageUrl` is set, the job hits `fal-ai/nano-banana/edit` with a hardened "do not change the product" wrapper around the prompt — combined with the lock clause that `ProductPromptGenerator` already appended, the product stays pixel-identical while the scene is restyled.

## Tuning

- **Bad outputs?** First check whether the theme is in `THEME_HINTS`. If not, add a row — that gives the biggest single quality jump for that occasion.
- **Drift / generic prompts?** Bump `SYSTEM_VERSION` and re-run with `--no-cache` while iterating on `buildSystem()` few-shot examples.
- **Costs?** Sonnet 4.6 at ~800 max-tokens with image input is roughly the right tier. Use `--model=claude-haiku-4-5-20251001` for cheap drafts; switch back to sonnet (or opus, when latency allows) for production runs.

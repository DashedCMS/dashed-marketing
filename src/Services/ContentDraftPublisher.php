<?php

namespace Dashed\DashedMarketing\Services;

use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedMarketing\Models\ContentDraft;
use Illuminate\Database\Eloquent\Model;

class ContentDraftPublisher
{
    /**
     * Per-request cache for available block options. Activating builder blocks
     * and iterating them costs several seconds — memoize so repeat calls
     * (modal open / settings page / publish) stay instant.
     *
     * @var array<string, string>|null
     */
    private static ?array $cachedBlockOptions = null;

    /**
     * Publish / re-sync a ContentDraft to its target model.
     *
     * $choices keys:
     *   - header:  block type to use for the title. Null/empty = no header block.
     *   - content: block type to use per section. Null/empty = no per-section blocks.
     *   - faq:     block type to use for FAQs. Null/empty = no FAQ block.
     */
    public function publish(ContentDraft $draft, Model $target, ?string $locale = null, array $choices = []): void
    {
        $locale = $locale ?: ($draft->locale ?: app()->getLocale());

        // Ensure block definitions (incl. their default toggle values) are loaded.
        cms()->activateBuilderBlockClasses();

        $draft->loadMissing(['sections', 'faqs']);

        $this->setTranslatable($target, 'name', $locale, (string) $draft->name);
        $this->setTranslatable($target, 'slug', $locale, (string) $draft->slug);

        $blocks = $this->buildBlocks($draft, $choices);
        $this->setTranslatable($target, 'content', $locale, $blocks);

        $target->save();

        $this->syncMetadata($draft, $target, $locale);
    }

    /**
     * Write the draft's meta_title / meta_description onto the target's
     * morph-one Metadata record for the given locale. Skipped silently
     * if the target doesn't expose a metadata() relation.
     */
    private function syncMetadata(ContentDraft $draft, Model $target, string $locale): void
    {
        if (! method_exists($target, 'metadata')) {
            return;
        }

        $title = trim((string) $draft->meta_title);
        $description = trim((string) $draft->meta_description);

        if ($title === '' && $description === '') {
            return;
        }

        $metadata = $target->metadata()->firstOrNew([]);

        if ($title !== '') {
            $metadata->setTranslation('title', $locale, $title);
        }

        if ($description !== '') {
            $metadata->setTranslation('description', $locale, $description);
        }

        $metadata->save();
    }

    /**
     * Build the block array from chosen block types. If a type is null/empty
     * for a category, that category is skipped entirely.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildBlocks(ContentDraft $draft, array $choices): array
    {
        $blocks = [];

        $headerType = $choices['header'] ?? null;
        if ($headerType) {
            $blocks[] = [
                'type' => (string) $headerType,
                'data' => array_merge($this->blockDefaults($headerType), [
                    'title' => (string) $draft->name,
                    'subtitle' => '',
                    'buttons' => [],
                ]),
            ];
        }

        $contentType = $choices['content'] ?? null;
        if ($contentType) {
            $contentDefaults = $this->blockDefaults($contentType);

            foreach ($draft->sections as $section) {
                $body = trim((string) ($section->body ?? ''));
                if ($body === '') {
                    continue;
                }

                $html = '<h2>'.e($section->heading).'</h2>'.$body;

                $blocks[] = [
                    'type' => (string) $contentType,
                    'data' => array_merge($contentDefaults, [
                        'content' => $html,
                    ]),
                ];
            }
        }

        $faqType = $choices['faq'] ?? null;
        if ($faqType && $draft->faqs->isNotEmpty()) {
            // Write the same items under both repeater keys we've seen in the wild
            // (`faqs` on the dashed-core default, `questions` on customised sites),
            // and populate both field-name pairs. Filament only renders the
            // one its schema actually reads — extras are harmless.
            $items = $draft->faqs->map(fn ($f) => [
                'question' => (string) $f->question,
                'description' => (string) $f->answer,
                'title' => (string) $f->question,
                'content' => (string) $f->answer,
            ])->values()->all();

            $blocks[] = [
                'type' => (string) $faqType,
                'data' => array_merge($this->blockDefaults($faqType), [
                    'title' => 'Veelgestelde vragen',
                    'subtitle' => '',
                    'columns' => 1,
                    'questions' => $items,
                    'faqs' => $items,
                ]),
            ];
        }

        return $blocks;
    }

    /**
     * Defaults that every block should start with. Mirrors the Filament toggles
     * injected by `AppServiceProvider::getDefaultBlockFields()` so that blocks
     * we build programmatically match what the admin UI would create.
     *
     * Extract from the Filament schema isn't practical here — Filament
     * components need a container context before their defaults can be read.
     *
     * @return array<string, mixed>
     */
    private function blockDefaults(string $blockName): array
    {
        return [
            'in_container' => true,
            'top_margin' => true,
            'bottom_margin' => true,
        ];
    }

    /**
     * Block-type options for Filament Selects, shaped as [key => label].
     *
     * @return array<string, string>
     */
    public static function availableBlockOptions(): array
    {
        if (self::$cachedBlockOptions !== null) {
            return self::$cachedBlockOptions;
        }

        $options = [];

        try {
            // Block definitions are lazy-registered by each package's builder class.
            // Trigger that registration before reading the list.
            cms()->activateBuilderBlockClasses();

            foreach ((array) cms()->builder('blocks') as $block) {
                $name = null;
                $label = null;

                if (is_object($block)) {
                    if (method_exists($block, 'getName')) {
                        $name = $block->getName();
                    }
                    if (method_exists($block, 'getLabel')) {
                        $label = $block->getLabel();
                    }
                } elseif (is_string($block)) {
                    $name = $block;
                }

                if (! $name) {
                    continue;
                }

                $options[$name] = (string) ($label ?: $name);
            }
        } catch (\Throwable) {
            // fall through, return whatever we have
        }

        ksort($options);

        return self::$cachedBlockOptions = $options;
    }

    /**
     * Default block choices — reads from settings first, falls back to sensible guesses.
     *
     * @return array{header: ?string, content: ?string, faq: ?string}
     */
    public static function defaultChoices(): array
    {
        $options = self::availableBlockOptions();

        $fallback = fn (array $preferred) => collect($preferred)->first(fn ($key) => isset($options[$key]));

        return [
            'header' => Customsetting::get('marketing_publish_header_block') ?: $fallback(['header', 'hero']),
            'content' => Customsetting::get('marketing_publish_content_block') ?: $fallback(['content']),
            'faq' => Customsetting::get('marketing_publish_faq_block') ?: $fallback(['faq']),
        ];
    }

    /**
     * Write a value for a specific locale on a translatable attribute.
     * Works for both string and array attributes via spatie/laravel-translatable.
     */
    private function setTranslatable(Model $target, string $attribute, string $locale, mixed $value): void
    {
        if (method_exists($target, 'setTranslation')) {
            $target->setTranslation($attribute, $locale, $value);

            return;
        }

        $raw = $target->getAttributes()[$attribute] ?? null;
        $decoded = is_string($raw) ? (json_decode($raw, true) ?: [$locale => $raw]) : (is_array($raw) ? $raw : []);
        $decoded[$locale] = $value;
        $target->{$attribute} = $decoded;
    }
}

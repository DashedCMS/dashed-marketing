<?php

namespace Dashed\DashedMarketing\Filament\Actions;

use Dashed\DashedAi\Facades\Ai;
use Dashed\DashedMarketing\Models\SocialPost;
use Dashed\DashedMarketing\Services\ProductPromptGenerator;
use Dashed\DashedMarketing\Services\SocialContextBuilder;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class RegenerateImagePromptAction
{
    public static function make(): Action
    {
        return Action::make('regenerateImagePrompt')
            ->label('(Her)genereer met AI')
            ->icon('heroicon-m-sparkles')
            ->color('primary')
            ->modalHeading('Image prompt opnieuw genereren')
            ->modalSubmitActionLabel('Genereer')
            ->schema([
                TextInput::make('theme')
                    ->label('Thema / gelegenheid')
                    ->placeholder('Bijv: Koningsdag, Kerst, Lente, Moederdag, Halloween')
                    ->helperText('Voor seizoenen/feestdagen vult het systeem automatisch concrete iconografie in. Laat leeg voor een neutrale productshot.'),
                Textarea::make('product_context')
                    ->label('Productinfo (sterk aanbevolen)')
                    ->placeholder("Bijv: Lovora Family Figurine - gepersonaliseerde 3D-print van je gezin in matte cream PLA, 15cm hoog, minimalistisch silhouet, design afgeleid van Scandinavische modernisme. Belangrijkste verkooppunt: tastbaar familieportret als cadeau.")
                    ->helperText('Naam, materiaal, finish, maten, designtaal, USP. Hoe specifieker, hoe meer de prompt over JOUW product gaat in plaats van een generiek figuurtje. Auto-aangevuld vanuit gekoppeld product/page als beschikbaar.')
                    ->default(fn ($livewire) => self::buildProductContextDefault($livewire->record ?? null))
                    ->rows(4),
                Textarea::make('instructions')
                    ->label('Extra instructies (optioneel)')
                    ->placeholder('Bijv: donkerder, cinematisch, geen mensen, close-up op product')
                    ->rows(3),
            ])
            ->action(function (array $data, $livewire) {
                $record = $livewire->record ?? null;
                $record = $record instanceof SocialPost ? $record : null;

                try {
                    $formState = $livewire->form->getState();
                } catch (\Throwable $e) {
                    $formState = [];
                }

                $theme = trim((string) ($data['theme'] ?? ''));
                $instructions = trim((string) ($data['instructions'] ?? ''));
                $productContext = trim((string) ($data['product_context'] ?? ''));

                $prompt = $record
                    ? self::tryVisionGeneration($record, $theme, $instructions, $productContext)
                    : null;

                if ($prompt === null) {
                    $prompt = self::generate($record, $formState, $instructions);
                }

                if ($prompt === null) {
                    Notification::make()
                        ->title('Genereren mislukt')
                        ->body('De AI gaf geen bruikbare image prompt terug.')
                        ->danger()
                        ->send();

                    return;
                }

                if ($record) {
                    $record->update(['image_prompt' => $prompt]);
                    $livewire->record?->refresh();
                }
                self::syncFormField($livewire, 'image_prompt', $prompt);

                Notification::make()
                    ->title($record ? 'Image prompt gegenereerd en opgeslagen' : 'Image prompt gegenereerd')
                    ->body($record ? null : 'Sla de post op om de image prompt permanent te bewaren.')
                    ->success()
                    ->send();
            });
    }

    /**
     * Push a value into the Livewire form state via every available mechanism
     * so the field actually shows the new value AND a subsequent form save
     * writes it to the DB instead of stale form state.
     */
    private static function syncFormField($livewire, string $path, mixed $value): void
    {
        if (isset($livewire->data) && is_array($livewire->data)) {
            data_set($livewire->data, $path, $value);
        }

        $rootKey = explode('.', $path, 2)[0];

        try {
            $livewire->refreshFormData([$rootKey]);
        } catch (\Throwable $e) {
            if (method_exists($livewire, 'fillForm')) {
                try {
                    $livewire->fillForm($livewire->data ?? []);
                } catch (\Throwable) {
                    // data_set above is the fallback source of truth.
                }
            }
        }
    }

    /**
     * Vision-aware path: when the post has a product image on disk, send it
     * to Claude vision via ProductPromptGenerator so the prompt describes
     * the actual product (and instructs the image-edit model to preserve it).
     * Returns null when no usable image is available, when ProductPromptGenerator
     * throws, or when the resulting string is empty.
     */
    private static function tryVisionGeneration(SocialPost $record, string $theme, string $instructions, string $productContext): ?string
    {
        $imagePath = self::resolveImagePath($record);
        if (! $imagePath) {
            return null;
        }

        // If the user didn't supply product context, auto-derive from the
        // post's linked subject. Better than nothing.
        if ($productContext === '') {
            $productContext = self::buildProductContextDefault($record);
        }

        $productName = self::resolveProductName($record);

        try {
            $prompt = app(ProductPromptGenerator::class)->generate(
                $imagePath,
                $theme !== '' ? $theme : 'neutral product shot',
                array_filter([
                    'extra_instructions' => $instructions,
                    'product_context' => $productContext,
                    'product_name' => $productName,
                ], fn ($v) => $v !== null && $v !== ''),
            );
        } catch (\Throwable $e) {
            Log::warning('[product-prompt] vision path failed, falling back to text-only', [
                'post_id' => $record->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        return $prompt !== '' ? $prompt : null;
    }

    /**
     * Auto-build a structured product-context string from the post's linked
     * subject (Product, Page, etc.) so the AI prompt can anchor in real
     * product data without the user having to type it in every time.
     */
    private static function buildProductContextDefault(?SocialPost $record): string
    {
        if (! $record instanceof SocialPost) {
            return '';
        }

        $subject = null;
        if ($record->subject_type && $record->subject_id && class_exists($record->subject_type)) {
            try {
                $subject = $record->subject_type::find($record->subject_id);
            } catch (\Throwable) {
                $subject = null;
            }
        }

        if (! $subject) {
            return '';
        }

        $skip = ['id', 'created_at', 'updated_at', 'deleted_at', 'site_id', 'password', 'remember_token', 'api_token', 'metadata', 'images', 'image', 'image_path', 'images_per_ratio', 'translations'];
        $attributes = method_exists($subject, 'attributesToArray') ? $subject->attributesToArray() : (array) $subject;

        $name = $attributes['name'] ?? $attributes['title'] ?? null;
        if (is_array($name)) {
            $name = reset($name) ?: null;
        }

        $lines = [];
        if ($name) {
            $lines[] = 'Name: '.(string) $name;
        }

        $interesting = ['short_description', 'description', 'excerpt', 'subtitle', 'material', 'materials', 'finish', 'dimensions', 'size', 'sku', 'price', 'usp', 'tagline'];
        foreach ($interesting as $key) {
            if (! array_key_exists($key, $attributes)) {
                continue;
            }
            $value = $attributes[$key];
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }
            $value = trim(strip_tags((string) $value));
            if ($value === '') {
                continue;
            }
            if (mb_strlen($value) > 400) {
                $value = mb_substr($value, 0, 400).'…';
            }
            $lines[] = ucfirst(str_replace('_', ' ', $key)).': '.$value;
        }

        return implode("\n", $lines);
    }

    private static function resolveProductName(SocialPost $record): string
    {
        if (! $record->subject_type || ! $record->subject_id || ! class_exists($record->subject_type)) {
            return '';
        }

        try {
            $subject = $record->subject_type::find($record->subject_id);
        } catch (\Throwable) {
            return '';
        }

        if (! $subject) {
            return '';
        }

        $name = $subject->name ?? $subject->title ?? null;
        if (is_array($name)) {
            $name = reset($name) ?: null;
        }

        return is_string($name) ? trim($name) : '';
    }

    /**
     * Find a real local file path for the post's primary image. Supports the
     * legacy 'storage/...' prefix and the new disk-relative paths produced by
     * GenerateSocialImageJob (e.g. 'social-generated/123-...png').
     */
    private static function resolveImagePath(SocialPost $record): ?string
    {
        $images = is_array($record->images) ? $record->images : [];
        $candidate = $images[0] ?? $record->image_path;
        if (! is_string($candidate) || $candidate === '') {
            return null;
        }

        if (str_starts_with($candidate, 'http://') || str_starts_with($candidate, 'https://')) {
            return null;
        }

        $relative = str_starts_with($candidate, 'storage/')
            ? substr($candidate, strlen('storage/'))
            : $candidate;

        $disk = Storage::disk('public');
        if (! $disk->exists($relative)) {
            return null;
        }

        $absolute = $disk->path($relative);

        return is_file($absolute) ? $absolute : null;
    }

    private static function generate(?SocialPost $record, array $formState, string $instructions): ?string
    {
        $caption = (string) ($formState['caption'] ?? $record?->caption ?? '');
        $altText = (string) ($formState['alt_text'] ?? $record?->alt_text ?? '');
        $currentPrompt = (string) ($formState['image_prompt'] ?? $record?->image_prompt ?? '');
        $channels = $formState['channels'] ?? $record?->channels ?? [];
        $type = $formState['type'] ?? $record?->type ?? 'post';

        $subject = null;
        if ($record && $record->subject_type && $record->subject_id && class_exists($record->subject_type)) {
            $subject = $record->subject_type::find($record->subject_id);
        }

        $contextBuilder = new SocialContextBuilder;
        $context = $contextBuilder->build($type, is_array($channels) ? $channels : [], $subject);

        $captionSection = $caption !== '' ? "## Caption van de post\n{$caption}" : '';
        $altSection = $altText !== '' ? "## Alt-tekst\n{$altText}" : '';
        $currentSection = $currentPrompt !== '' ? "## Huidige image prompt\n{$currentPrompt}" : '';
        $instructionsSection = trim($instructions) !== ''
            ? "## Gebruikersinstructies\n".trim($instructions)
            : '';

        $prompt = <<<PROMPT
        {$context}

        {$captionSection}

        {$altSection}

        {$currentSection}

        {$instructionsSection}

        ## Opdracht
        Write ONE production-grade image prompt in ENGLISH for an AI image generator (flux / nano-banana).

        ## Quality bar (NON-NEGOTIABLE)
        The prompt MUST read like a senior product photographer's brief. That means:
        - Open with a concrete style declaration: "Photorealistic product photo of...", "Cinematic lifestyle shot of...", "Editorial flat-lay of...", "Documentary candid of...", "High-end studio still life of..." - whichever fits the brand.
        - Name the SUBJECT precisely (what's in frame, how many, in what arrangement).
        - Name a concrete SETTING - a real place, not "background" (e.g. "wooden table outside a typical Dutch canal house", "linen-covered marble countertop", "sunlit bedroom with sheer curtains").
        - Name 3+ concrete PROPS / scene elements that reinforce the post's theme. If the user mentions a holiday, season or event, EXPAND it into specific iconography (e.g. King's Day → "small orange tulips, a tiny Dutch flag, orange streamers, orange crown decorations"; Christmas → "pine sprigs, red berries, beeswax candles, linen napkins"). Never write vague phrases like "festive decorations" or "subtle accents".
        - Specify LIGHTING (e.g. "soft natural daylight", "golden hour", "moody overcast", "warm tungsten side-light").
        - Specify CAMERA / OPTICS: lens (e.g. "50mm", "85mm macro", "35mm wide"), depth of field, framing (close-up, three-quarter angle, overhead flat-lay).
        - End with a short BRAND VIBE line (e.g. "Cozy, premium lifestyle vibe", "minimalist Scandinavian, calm and quiet").
        - No text in the image unless the user instructions explicitly asked for it.
        - Aim for ~60-120 words, dense and concrete. NEVER output a generic abstract prompt; never echo a short user brief back - EXPAND it.

        Verwerk de gebruikersinstructies hierboven (indien aanwezig) volledig - als ze kort of thematisch zijn ("Koningsdag", "lente", "promoot de nieuwe bundel") moet je ze uitvouwen tot een complete concrete scène.

        Retourneer UITSLUITEND geldig JSON zonder uitleg of markdown:
        {
            "image_prompt": "English image prompt"
        }
        PROMPT;

        $result = Ai::json($prompt);

        if (! is_array($result) || ! isset($result['image_prompt'])) {
            return null;
        }

        return trim((string) $result['image_prompt']);
    }
}

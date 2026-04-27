<?php

namespace Dashed\DashedMarketing\Filament\Actions;

use Dashed\DashedAi\Facades\Ai;
use Dashed\DashedMarketing\Models\SocialChannel;
use Dashed\DashedMarketing\Models\SocialPost;
use Dashed\DashedMarketing\Services\SocialContextBuilder;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

class RegenerateCaptionAction
{
    public static function forDefault(): Action
    {
        return self::base('regenerateDefaultCaption', 'caption', null);
    }

    public static function forChannel(string $slug): Action
    {
        return self::base("regenerateChannelCaption_{$slug}", "channel_captions.{$slug}", $slug);
    }

    private static function base(string $name, string $fieldPath, ?string $channelSlug): Action
    {
        return Action::make($name)
            ->label('(Her)genereer met AI')
            ->icon('heroicon-m-sparkles')
            ->color('primary')
            ->modalHeading('Caption opnieuw genereren')
            ->modalSubmitActionLabel('Genereer')
            ->schema([
                Textarea::make('instructions')
                    ->label('Instructies (optioneel)')
                    ->placeholder('Bijv: maak 20% korter, gebruik een vraag als opener, focus op duurzaamheid')
                    ->rows(4),
            ])
            ->action(function (array $data, $livewire) use ($channelSlug) {
                $record = $livewire->record ?? null;
                $record = $record instanceof SocialPost ? $record : null;

                try {
                    $formState = $livewire->form->getState();
                } catch (\Throwable $e) {
                    $formState = [];
                }

                $newCaption = self::generate(
                    $record,
                    $channelSlug,
                    $formState,
                    (string) ($data['instructions'] ?? ''),
                );

                if ($newCaption === null) {
                    Notification::make()
                        ->title('Genereren mislukt')
                        ->body('De AI gaf geen bruikbare caption terug.')
                        ->danger()
                        ->send();

                    return;
                }

                if ($channelSlug === null) {
                    if ($record) {
                        $record->update(['caption' => $newCaption]);
                        $livewire->record?->refresh();
                    }
                    self::syncFormField($livewire, 'caption', $newCaption);
                } else {
                    $current = $record
                        ? ($record->channel_captions ?? [])
                        : (array) ($formState['channel_captions'] ?? []);
                    $current[$channelSlug] = $newCaption;

                    if ($record) {
                        $record->update(['channel_captions' => $current]);
                        $livewire->record?->refresh();
                    }
                    self::syncFormField($livewire, 'channel_captions', $current);
                    self::syncFormField($livewire, "channel_captions.{$channelSlug}", $newCaption);
                }

                Notification::make()
                    ->title($record ? 'Caption gegenereerd en opgeslagen' : 'Caption gegenereerd')
                    ->body($record ? null : 'Sla de post op om de caption permanent te bewaren.')
                    ->success()
                    ->send();
            });
    }

    /**
     * Push a value into the Livewire component's form state by every available
     * mechanism so the textarea actually shows the new caption AND a subsequent
     * form save writes the new caption to the DB instead of the stale form state.
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
            // Fall back to a fillForm hydration; on Create pages refreshFormData may
            // throw because there is no bound record.
            if (method_exists($livewire, 'fillForm')) {
                try {
                    $livewire->fillForm($livewire->data ?? []);
                } catch (\Throwable) {
                    // Last resort: leave the data_set above as the source of truth.
                }
            }
        }
    }

    private static function generate(?SocialPost $record, ?string $channelSlug, array $formState, string $instructions): ?string
    {
        $currentCaption = $channelSlug === null
            ? (string) ($formState['caption'] ?? $record?->caption ?? '')
            : (string) ($formState['channel_captions'][$channelSlug] ?? ($record?->channel_captions[$channelSlug] ?? '') ?? '');

        $defaultCaption = (string) ($formState['caption'] ?? $record?->caption ?? '');
        $channels = $formState['channels'] ?? $record?->channels ?? [];
        $type = $formState['type'] ?? $record?->type ?? 'post';

        $subject = null;
        if ($record && $record->subject_type && $record->subject_id && class_exists($record->subject_type)) {
            $subject = $record->subject_type::find($record->subject_id);
        }

        $contextBuilder = new SocialContextBuilder;
        $context = $contextBuilder->build($type, is_array($channels) ? $channels : [], $subject);

        $channelRules = '';
        $channelFocusSection = '';
        if ($channelSlug !== null) {
            $channel = SocialChannel::query()
                ->withoutGlobalScopes()
                ->where('slug', $channelSlug)
                ->first();

            if ($channel) {
                $meta = $channel->meta ?? [];
                $min = $meta['caption_min'] ?? 0;
                $max = $meta['caption_max'] ?? 0;
                $tips = $meta['tips'] ?? '';
                $channelRules = "- {$channel->slug} ({$channel->name}): {$min}-{$max} tekens. {$tips}";
                $channelFocusSection = "Schrijf specifiek voor kanaal **{$channel->name}** ({$channel->slug}). Respecteer de karakterlimiet strikt.";
            }
        } else {
            $channelFocusSection = 'Schrijf een generieke caption die als standaard fungeert voor alle kanalen.';
        }

        $existingSection = $currentCaption !== ''
            ? "## Huidige caption (als startpunt, maar pas aan volgens de instructies)\n{$currentCaption}"
            : '';

        $defaultContextSection = ($channelSlug !== null && $defaultCaption !== '' && $defaultCaption !== $currentCaption)
            ? "## Default caption van deze post (voor context)\n{$defaultCaption}"
            : '';

        $instructionsSection = trim($instructions) !== ''
            ? "## Gebruikersinstructies\n".trim($instructions)
            : '';

        $prompt = <<<PROMPT
        {$context}

        {$existingSection}

        {$defaultContextSection}

        {$instructionsSection}

        ## Opdracht
        Herschrijf de caption voor deze social media post. {$channelFocusSection}

        Houd je aan dezelfde kwaliteitseisen: sterke hook, concrete waarde, platform-passende toon, korte zinnen, duidelijke CTA, geen AI-clichés. Verwijs NOOIT naar "link in bio", "linkje in bio", "swipe up", "tap de link" of vergelijkbare bio-/link-verwijzingen.

        {$channelRules}

        Retourneer UITSLUITEND geldig JSON zonder uitleg of markdown:
        {
            "caption": "de nieuwe caption"
        }
        PROMPT;

        $result = Ai::json($prompt);

        if (! is_array($result) || ! isset($result['caption'])) {
            return null;
        }

        return trim((string) $result['caption']);
    }
}

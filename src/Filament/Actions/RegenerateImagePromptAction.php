<?php

namespace Dashed\DashedMarketing\Filament\Actions;

use Dashed\DashedAi\Facades\Ai;
use Dashed\DashedMarketing\Models\SocialPost;
use Dashed\DashedMarketing\Services\SocialContextBuilder;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

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
                Textarea::make('instructions')
                    ->label('Instructies (optioneel)')
                    ->placeholder('Bijv: donkerder, cinematisch, geen mensen, close-up op product')
                    ->rows(4),
            ])
            ->action(function (array $data, $livewire) {
                $record = $livewire->record ?? null;
                if (! $record instanceof SocialPost) {
                    Notification::make()
                        ->title('Kan record niet vinden')
                        ->danger()
                        ->send();

                    return;
                }

                try {
                    $formState = $livewire->form->getState();
                } catch (\Throwable $e) {
                    $formState = [];
                }

                $prompt = self::generate(
                    $record,
                    $formState,
                    (string) ($data['instructions'] ?? ''),
                );

                if ($prompt === null) {
                    Notification::make()
                        ->title('Genereren mislukt')
                        ->body('De AI gaf geen bruikbare image prompt terug.')
                        ->danger()
                        ->send();

                    return;
                }

                $record->update(['image_prompt' => $prompt]);
                $livewire->refreshFormData(['image_prompt']);

                Notification::make()
                    ->title('Image prompt gegenereerd')
                    ->success()
                    ->send();
            });
    }

    private static function generate(SocialPost $record, array $formState, string $instructions): ?string
    {
        $caption = (string) ($formState['caption'] ?? $record->caption ?? '');
        $altText = (string) ($formState['alt_text'] ?? $record->alt_text ?? '');
        $currentPrompt = (string) ($formState['image_prompt'] ?? $record->image_prompt ?? '');
        $channels = $formState['channels'] ?? $record->channels ?? [];
        $type = $formState['type'] ?? $record->type ?? 'post';

        $subject = null;
        if ($record->subject_type && $record->subject_id && class_exists($record->subject_type)) {
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
        Schrijf één gedetailleerde image prompt in het Engels voor een AI image generator. Beschrijf subject, compositie, lighting, color palette, mood en stijl. Moet visueel passen bij de caption, het merk en de toon. Geen tekst in het beeld tenzij expliciet gevraagd in de instructies.

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

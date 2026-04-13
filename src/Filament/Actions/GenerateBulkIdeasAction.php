<?php

namespace Dashed\DashedMarketing\Filament\Actions;

use Dashed\DashedAi\Facades\Ai;
use Dashed\DashedMarketing\Models\SocialIdea;
use Dashed\DashedMarketing\Services\SocialContextBuilder;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

class GenerateBulkIdeasAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'generateBulkIdeas';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Genereer ideeën met AI')
            ->icon('heroicon-o-sparkles')
            ->color('warning')
            ->form([
                Select::make('period')
                    ->label('Periode')
                    ->options([
                        1 => '1 week',
                        2 => '2 weken',
                        4 => '4 weken',
                    ])
                    ->default(2)
                    ->required(),

                TextInput::make('count')
                    ->label('Aantal ideeën')
                    ->numeric()
                    ->minValue(3)
                    ->maxValue(30)
                    ->default(10)
                    ->required(),

                Textarea::make('focus')
                    ->label('Focus / thema (optioneel)')
                    ->placeholder('Bijv: zomercollectie, Black Friday, duurzaamheid...')
                    ->rows(2)
                    ->nullable(),
            ])
            ->action(function (array $data): void {
                $contextBuilder = new SocialContextBuilder;
                $context = $contextBuilder->build();

                $period = $data['period'];
                $count = $data['count'];
                $focus = $data['focus'] ?? null;
                $focusLine = $focus ? "Focus voor deze periode: {$focus}\n" : '';

                $prompt = <<<PROMPT
                {$context}

                {$focusLine}
                Genereer {$count} concrete social media ideeën voor de komende {$period} week(en).
                Varieer in platform, content pijler, en type (tips, behind-the-scenes, product, inspiratie, humor).

                Retourneer UITSLUITEND geldig JSON in dit formaat:
                {
                    "ideas": [
                        {
                            "title": "Korte beschrijvende titel",
                            "platform": "instagram_feed",
                            "notes": "Korte toelichting op het idee en aanpak",
                            "tags": ["tag1", "tag2"]
                        }
                    ]
                }
                PROMPT;

                $result = Ai::json($prompt);

                if (! $result || empty($result['ideas'])) {
                    Notification::make()
                        ->title('Genereren mislukt')
                        ->body('De AI provider gaf geen bruikbaar antwoord. Controleer de AI instellingen.')
                        ->danger()
                        ->send();

                    return;
                }

                $created = 0;
                foreach ($result['ideas'] as $idea) {
                    SocialIdea::create([
                        'title' => $idea['title'] ?? 'Onbekend idee',
                        'platform' => $idea['platform'] ?? null,
                        'notes' => $idea['notes'] ?? null,
                        'tags' => $idea['tags'] ?? [],
                        'status' => 'idea',
                    ]);
                    $created++;
                }

                Notification::make()
                    ->title("{$created} ideeën aangemaakt")
                    ->success()
                    ->send();
            });
    }
}

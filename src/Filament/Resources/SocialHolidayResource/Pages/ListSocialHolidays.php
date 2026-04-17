<?php

namespace Dashed\DashedMarketing\Filament\Resources\SocialHolidayResource\Pages;

use Filament\Actions\Action;
use Dashed\DashedAi\Facades\Ai;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Dashed\DashedMarketing\Models\SocialHoliday;
use Dashed\DashedMarketing\Filament\Resources\SocialHolidayResource;

class ListSocialHolidays extends ListRecords
{
    protected static string $resource = SocialHolidayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importHolidays')
                ->label('Importeer feestdagen met AI')
                ->icon('heroicon-o-sparkles')
                ->color('primary')
                ->form([
                    Select::make('country')
                        ->label('Land')
                        ->options([
                            'NL' => 'Nederland',
                            'BE' => 'België',
                            'DE' => 'Duitsland',
                        ])
                        ->required()
                        ->default('NL'),
                    TextInput::make('year')
                        ->label('Jaar')
                        ->numeric()
                        ->required()
                        ->default(now()->year),
                ])
                ->action(function (array $data): void {
                    $country = $data['country'];
                    $year = $data['year'];
                    $countryName = match ($country) {
                        'NL' => 'Nederland',
                        'BE' => 'België',
                        'DE' => 'Duitsland',
                        default => $country,
                    };

                    $prompt = <<<PROMPT
                    Geef alle relevante feestdagen en commerciële momenten voor {$countryName} in {$year}.
                    Denk aan: officiële feestdagen, seizoensmomenten (begin lente/zomer/herfst/winter),
                    commerciële dagen (Valentijnsdag, Moederdag, Vaderdag, Black Friday, Sinterklaas, etc.),
                    en lokale tradities.

                    Retourneer UITSLUITEND geldig JSON:
                    {
                        "holidays": [
                            {"name": "Nieuwjaarsdag", "date": "{$year}-01-01"},
                            {"name": "Valentijnsdag", "date": "{$year}-02-14"}
                        ]
                    }
                    PROMPT;

                    $result = Ai::json($prompt);

                    if (! $result || empty($result['holidays'])) {
                        Notification::make()
                            ->title('Importeren mislukt')
                            ->body('De AI provider gaf geen bruikbaar antwoord.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $created = 0;
                    $skipped = 0;

                    foreach ($result['holidays'] as $holiday) {
                        $exists = SocialHoliday::where('name', $holiday['name'])
                            ->where('date', $holiday['date'])
                            ->where('country', $country)
                            ->exists();

                        if ($exists) {
                            $skipped++;

                            continue;
                        }

                        SocialHoliday::create([
                            'name' => $holiday['name'],
                            'date' => $holiday['date'],
                            'country' => $country,
                            'auto_remind' => true,
                            'remind_days_before' => 21,
                        ]);
                        $created++;
                    }

                    Notification::make()
                        ->title("{$created} feestdagen geïmporteerd")
                        ->body($skipped > 0 ? "{$skipped} bestaande feestdagen overgeslagen." : '')
                        ->success()
                        ->send();
                }),
            CreateAction::make(),
        ];
    }
}

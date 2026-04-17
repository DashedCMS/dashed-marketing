<?php

namespace Dashed\DashedMarketing\Filament\Imports;

use Filament\Forms\Components\Select;
use Filament\Actions\Imports\Importer;
use Dashed\DashedMarketing\Models\Keyword;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Models\Import;

class KeywordImporter extends Importer
{
    protected static ?string $model = Keyword::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('keyword')
                ->label('Zoekwoord')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255'])
                ->guess(['keyword', 'zoekwoord', 'term'])
                ->example('theelichthouder'),

            ImportColumn::make('volume_exact')
                ->label('Volume')
                ->integer()
                ->guess(['volume', 'volume_exact', 'search volume', 'searches'])
                ->example('1200'),

            ImportColumn::make('search_intent')
                ->label('Intent')
                ->guess(['intent', 'search intent', 'search_intent'])
                ->example('commercial'),

            ImportColumn::make('difficulty')
                ->label('Difficulty')
                ->guess(['difficulty', 'kd', 'keyword difficulty'])
                ->example('medium'),

            ImportColumn::make('cpc')
                ->label('CPC')
                ->numeric(decimalPlaces: 2)
                ->guess(['cpc'])
                ->example('0.45'),

            ImportColumn::make('notes')
                ->label('Notities')
                ->guess(['notes', 'note', 'opmerking'])
                ->example('Vervangt waxinelicht synoniem'),
        ];
    }

    public static function getOptionsFormComponents(): array
    {
        return [
            Select::make('locale')
                ->label('Taal')
                ->options(['nl' => 'Nederlands', 'en' => 'English'])
                ->default(config('app.locale', 'nl'))
                ->required(),
            Select::make('duplicate_strategy')
                ->label('Bij dubbele zoekwoorden')
                ->options([
                    'skip' => 'Overslaan',
                    'update' => 'Bijwerken',
                ])
                ->default('skip')
                ->required(),
        ];
    }

    public function resolveRecord(): ?Keyword
    {
        $locale = $this->options['locale'] ?? config('app.locale', 'nl');

        $existing = Keyword::query()
            ->where('locale', $locale)
            ->where('keyword', $this->data['keyword'] ?? '')
            ->first();

        if ($existing) {
            if (($this->options['duplicate_strategy'] ?? 'skip') === 'skip') {
                return null;
            }

            return $existing;
        }

        return new Keyword([
            'locale' => $locale,
            'type' => 'secondary',
            'volume_indication' => 'medium',
            'source' => 'csv',
            'status' => 'new',
        ]);
    }

    public static function getCompletedNotificationTitle(Import $import): string
    {
        return 'Zoekwoorden import klaar';
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = number_format($import->successful_rows).' van '.number_format($import->total_rows).' zoekwoorden geïmporteerd.';

        if ($failed = $import->getFailedRowsCount()) {
            $body .= ' '.number_format($failed).' regels mislukt.';
        }

        return $body;
    }
}

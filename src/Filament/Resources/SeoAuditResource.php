<?php

namespace Dashed\DashedMarketing\Filament\Resources;

use BackedEnum;
use Dashed\DashedMarketing\Filament\Resources\SeoAuditResource\Pages\ListSeoAudits;
use Dashed\DashedMarketing\Filament\Resources\SeoAuditResource\Pages\ReviewSeoAudit;
use Dashed\DashedMarketing\Models\SeoAudit;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class SeoAuditResource extends Resource
{
    protected static ?string $model = SeoAudit::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-magnifying-glass-circle';

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = 'SEO audits';

    protected static ?string $label = 'SEO audit';

    protected static ?string $pluralLabel = 'SEO audits';

    protected static ?int $navigationSort = 5;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('subject_type')
                    ->label('Type')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => class_basename((string) $state)),
                TextColumn::make('subject_id')
                    ->label('Onderwerp')
                    ->sortable()
                    ->searchable(query: function ($query, string $search) {
                        $query->where('subject_id', $search);
                    })
                    ->getStateUsing(function ($record) {
                        $s = $record->subject;
                        if (! $s) {
                            return 'Record #'.$record->subject_id;
                        }
                        $name = $s->name ?? $s->title ?? 'Record #'.$s->getKey();
                        if (is_array($name)) {
                            $name = $name[app()->getLocale()] ?? reset($name) ?? ('Record #'.$s->getKey());
                        }

                        return (string) $name;
                    }),
                TextColumn::make('status')
                    ->label('Status')
                    ->sortable()
                    ->badge()
                    ->formatStateUsing(fn ($record) => $record->status_label)
                    ->color(fn ($record) => $record->status_color),
                TextColumn::make('overall_score')
                    ->label('Score')
                    ->sortable()
                    ->badge()
                    ->color(fn ($state) => $state !== null && $state >= 80 ? 'success' : ($state !== null && $state >= 60 ? 'warning' : 'danger'))
                    ->default('-'),
                TextColumn::make('created_at')
                    ->label('Aangemaakt')
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),
                TextColumn::make('applied_at')
                    ->label('Toegepast')
                    ->dateTime('d-m-Y H:i')
                    ->sortable()
                    ->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('subject_type')
                    ->label('Type')
                    ->options(fn () => SeoAudit::query()
                        ->distinct()
                        ->pluck('subject_type')
                        ->mapWithKeys(fn ($t) => [$t => class_basename((string) $t)])
                        ->toArray()
                    ),
                SelectFilter::make('status')->options([
                    'analyzing' => 'Analyseren',
                    'ready' => 'Klaar',
                    'partially_applied' => 'Gedeeltelijk toegepast',
                    'fully_applied' => 'Volledig toegepast',
                    'archived' => 'Gearchiveerd',
                    'failed' => 'Mislukt',
                ]),
                SelectFilter::make('score_bucket')
                    ->label('Score')
                    ->options([
                        'high' => '80-100 (goed)',
                        'medium' => '60-79 (matig)',
                        'low' => '0-59 (slecht)',
                        'none' => 'Geen score',
                    ])
                    ->query(function ($query, array $data) {
                        $value = $data['value'] ?? null;
                        match ($value) {
                            'high' => $query->where('overall_score', '>=', 80),
                            'medium' => $query->whereBetween('overall_score', [60, 79]),
                            'low' => $query->where('overall_score', '<', 60)->whereNotNull('overall_score'),
                            'none' => $query->whereNull('overall_score'),
                            default => null,
                        };
                    }),
                TernaryFilter::make('applied_at')
                    ->label('Toegepast')
                    ->placeholder('Alle')
                    ->trueLabel('Toegepast')
                    ->falseLabel('Niet toegepast')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('applied_at'),
                        false: fn ($query) => $query->whereNull('applied_at'),
                    ),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn ($record) => static::getUrl('review', ['record' => $record->id]))
            ->recordActions([
                Action::make('review')
                    ->label('Review')
                    ->icon('heroicon-o-eye')
                    ->url(fn ($record) => static::getUrl('review', ['record' => $record->id])),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSeoAudits::route('/'),
            'review' => ReviewSeoAudit::route('/{record}/review'),
        ];
    }
}

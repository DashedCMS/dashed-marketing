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
                    ->formatStateUsing(fn ($state) => class_basename((string) $state)),
                TextColumn::make('subject_label')
                    ->label('Onderwerp')
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
                    ->badge()
                    ->formatStateUsing(fn ($record) => $record->status_label)
                    ->color(fn ($record) => $record->status_color),
                TextColumn::make('overall_score')
                    ->label('Score')
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
                    ->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'analyzing' => 'Analyseren',
                    'ready' => 'Klaar',
                    'partially_applied' => 'Gedeeltelijk toegepast',
                    'fully_applied' => 'Volledig toegepast',
                    'archived' => 'Gearchiveerd',
                    'failed' => 'Mislukt',
                ]),
            ])
            ->defaultSort('created_at', 'desc')
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

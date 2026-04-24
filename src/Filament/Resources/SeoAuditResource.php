<?php

namespace Dashed\DashedMarketing\Filament\Resources;

use BackedEnum;
use Dashed\DashedMarketing\Filament\Resources\SeoAuditResource\Pages\ListSeoAudits;
use Dashed\DashedMarketing\Filament\Resources\SeoAuditResource\Pages\ReviewSeoAudit;
use Dashed\DashedMarketing\Jobs\GenerateSeoAuditJob;
use Dashed\DashedMarketing\Models\SeoAudit;
use Dashed\DashedMarketing\Services\SeoAuditApplier;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
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
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('archive')
                        ->label('Archiveren')
                        ->icon('heroicon-o-archive-box')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->modalHeading('Geselecteerde audits archiveren')
                        ->modalDescription('Audits krijgen status archived; suggesties, logs en rollback-historie blijven bewaard.')
                        ->action(function (Collection $records) {
                            $count = 0;
                            foreach ($records as $record) {
                                if ($record->status === 'archived') {
                                    continue;
                                }
                                $record->update(['status' => 'archived', 'archived_at' => now()]);
                                $count++;
                            }
                            Notification::make()
                                ->title("{$count} audits gearchiveerd")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('regenerate')
                        ->label('Opnieuw genereren')
                        ->icon('heroicon-o-arrow-path')
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalHeading('Audits opnieuw genereren')
                        ->modalDescription('Voor elk geselecteerd audit start een nieuwe analyse op hetzelfde onderwerp met dezelfde taal en instructie. Het bestaande audit wordt gearchiveerd.')
                        ->action(function (Collection $records) {
                            $count = 0;
                            foreach ($records as $record) {
                                if (! class_exists($record->subject_type)) {
                                    continue;
                                }
                                GenerateSeoAuditJob::dispatch(
                                    $record->subject_type,
                                    $record->subject_id,
                                    auth()->id(),
                                    $record->instruction,
                                    $record->locale,
                                );
                                $count++;
                            }
                            Notification::make()
                                ->title("{$count} audits opnieuw gestart")
                                ->body('De nieuwe analyses draaien op de achtergrond.')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('apply_all')
                        ->label('Alles toepassen')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Alle voorstellen toepassen voor geselecteerde audits')
                        ->modalDescription('Let op: bij block/FAQ-apply worden bestaande blokken op de pagina eerst gewist voordat de suggesties worden geplaatst. Alleen audits met status ready, partially_applied of fully_applied worden verwerkt.')
                        ->action(function (Collection $records) {
                            $applier = app(SeoAuditApplier::class);
                            $applied = 0;
                            $skipped = 0;
                            $failed = 0;

                            foreach ($records as $record) {
                                if (! in_array($record->status, ['ready', 'partially_applied', 'fully_applied'], true)) {
                                    $skipped++;

                                    continue;
                                }
                                try {
                                    $applier->applyAll($record, auth()->id());
                                    $applied++;
                                } catch (\Throwable $e) {
                                    $failed++;
                                }
                            }

                            Notification::make()
                                ->title("Bulk apply: {$applied} toegepast, {$skipped} overgeslagen, {$failed} mislukt")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
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

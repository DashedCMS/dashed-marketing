<?php

namespace Dashed\DashedMarketing\Filament\Actions;

use Dashed\DashedMarketing\Filament\Resources\SeoAuditResource;
use Dashed\DashedMarketing\Jobs\GenerateSeoAuditJob;
use Dashed\DashedMarketing\Models\SeoAudit;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

class RequestSeoAuditAction
{
    /**
     * Header-action die op elke CMS editpage via HasEditableCMSActions
     * beschikbaar is. Start een nieuwe SeoAudit-job voor het huidige record.
     */
    public static function make(): Action
    {
        return Action::make('request_seo_audit')
            ->label('Genereer SEO audit')
            ->icon('heroicon-o-magnifying-glass-circle')
            ->color('info')
            ->visible(function ($livewire) {
                $record = $livewire?->record ?? null;

                return $record !== null && $record->exists;
            })
            ->modalHeading('SEO audit genereren')
            ->modalDescription(function ($livewire) {
                $record = $livewire?->record ?? null;
                if (! $record) {
                    return 'AI analyseert dit item op zeven SEO-domeinen.';
                }

                $existing = SeoAudit::where('subject_type', $record::class)
                    ->where('subject_id', $record->getKey())
                    ->whereNotIn('status', ['archived', 'failed'])
                    ->first();

                return $existing
                    ? 'Er ligt al een audit. Deze wordt gearchiveerd voor de nieuwe begint.'
                    : 'AI analyseert dit item op zeven SEO-domeinen. Je kunt daarna per voorstel accepteren of afwijzen.';
            })
            ->modalSubmitActionLabel('Start analyse')
            ->schema([
                Textarea::make('instruction')
                    ->label('Instructie (optioneel)')
                    ->placeholder('Bijv. focus op lokale SEO Amsterdam.')
                    ->rows(3),
            ])
            ->action(function (array $data, $livewire) {
                $record = $livewire->record ?? null;
                if (! $record || ! $record->exists) {
                    Notification::make()
                        ->title('Geen record om te analyseren')
                        ->danger()
                        ->send();

                    return;
                }

                $instruction = ! empty($data['instruction']) ? (string) $data['instruction'] : null;

                GenerateSeoAuditJob::dispatch(
                    $record::class,
                    $record->getKey(),
                    auth()->id(),
                    $instruction,
                );

                Notification::make()
                    ->title('SEO analyse gestart')
                    ->body('Zodra klaar verschijnt het voorstel onder Marketing → SEO audits.')
                    ->success()
                    ->actions([
                        \Filament\Notifications\Actions\Action::make('goto_list')
                            ->label('Naar SEO audits')
                            ->url(SeoAuditResource::getUrl('index')),
                    ])
                    ->send();
            });
    }
}

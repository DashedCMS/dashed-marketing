<?php

namespace Dashed\DashedMarketing\Filament\Actions;

use Dashed\DashedMarketing\Filament\Resources\SeoImprovementResource;
use Dashed\DashedMarketing\Jobs\AnalyzeSubjectSeoJob;
use Dashed\DashedMarketing\Models\SeoImprovement;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

class RequestSeoImprovementAction
{
    /**
     * Filament header action that triggers an AI SEO analysis for the
     * record currently being edited, and kicks the user to the review
     * page for the resulting SeoImprovement.
     */
    public static function make(): Action
    {
        return Action::make('request_seo_improvement')
            ->label('Genereer SEO verbetervoorstel')
            ->icon('heroicon-o-sparkles')
            ->color('info')
            ->visible(function ($livewire) {
                $record = $livewire?->record ?? null;

                return $record !== null && $record->exists;
            })
            ->modalHeading('SEO verbetervoorstel genereren')
            ->modalDescription('Laat AI het huidige item analyseren. Je kunt daarna per veld bekijken of je de voorgestelde wijziging accepteert of afwijst.')
            ->modalSubmitActionLabel('Start analyse')
            ->schema([
                Textarea::make('instruction')
                    ->label('Instructie (optioneel)')
                    ->placeholder('Bijv. focus op lokale SEO, mik op het keyword "leren tas op maat".')
                    ->rows(3),
            ])
            ->action(function (array $data, $livewire) {
                $record = $livewire->record ?? null;
                if (! $record || ! $record->exists) {
                    Notification::make()
                        ->title('Geen record gevonden om te analyseren')
                        ->danger()
                        ->send();

                    return;
                }

                AnalyzeSubjectSeoJob::dispatch(
                    $record::class,
                    $record->getKey(),
                    auth()->id(),
                    ! empty($data['instruction']) ? (string) $data['instruction'] : null,
                );

                $existing = SeoImprovement::where('subject_type', $record::class)
                    ->where('subject_id', $record->getKey())
                    ->first();

                $notification = Notification::make()
                    ->title('SEO analyse gestart')
                    ->body('Zodra klaar verschijnt het voorstel onder Marketing → SEO verbeteringen.')
                    ->success();

                if ($existing) {
                    $notification->actions([
                        \Filament\Notifications\Actions\Action::make('view')
                            ->label('Bekijk voorstel')
                            ->url(SeoImprovementResource::getUrl('edit', ['record' => $existing->id])),
                    ]);
                }

                $notification->send();
            });
    }
}

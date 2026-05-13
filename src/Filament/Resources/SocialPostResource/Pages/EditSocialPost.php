<?php

namespace Dashed\DashedMarketing\Filament\Resources\SocialPostResource\Pages;

use Dashed\DashedMarketing\Filament\Actions\GenerateImageAction;
use Dashed\DashedMarketing\Filament\Resources\SocialPostResource;
use Dashed\DashedMarketing\Jobs\PublishSocialPostJob;
use Dashed\DashedMarketing\Models\SocialPost;
use Dashed\DashedMarketing\Models\SocialPostVersion;
use Dashed\DashedOmnisocials\Jobs\RefreshAnalyticsJob;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditSocialPost extends EditRecord
{
    protected static string $resource = SocialPostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('publish')
                ->label('Publiceer nu')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->visible(fn () => in_array($this->record->status, ['concept', 'approved', 'publish_failed']))
                ->requiresConfirmation()
                ->modalHeading('Post publiceren?')
                ->modalDescription('De post wordt via de actieve adapter gepubliceerd naar de geselecteerde kanalen.')
                ->action(function () {
                    $this->record->update(['scheduled_at' => now()]);
                    $this->refreshFormData(['scheduled_at']);

                    PublishSocialPostJob::dispatch($this->record);

                    Notification::make()
                        ->title('Publicatie gestart')
                        ->success()
                        ->send();
                }),
            Action::make('retry')
                ->label('Opnieuw proberen')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn () => $this->record->status === 'partially_posted')
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->update(['retry_count' => 0]);
                    PublishSocialPostJob::dispatch($this->record);

                    Notification::make()
                        ->title('Retry gestart')
                        ->success()
                        ->send();
                }),
            Action::make('sharePost')
                ->label('Deel post')
                ->icon('heroicon-o-share')
                ->color('info')
                ->modalHeading('Deel post')
                ->modalDescription('Kopieer de caption, download de afbeelding en open de post URL.')
                ->modalWidth('3xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Sluiten')
                ->modalContent(fn (SocialPost $record) => view(
                    'dashed-marketing::filament.modals.share-post',
                    ['record' => $record],
                )),
            Action::make('refreshAnalytics')
                ->label('Analytics verversen')
                ->icon('heroicon-o-chart-bar')
                ->color('gray')
                ->visible(fn () => $this->record->external_id !== null)
                ->action(function () {
                    if (class_exists(RefreshAnalyticsJob::class)) {
                        RefreshAnalyticsJob::dispatch($this->record);
                    }

                    Notification::make()
                        ->title('Analytics worden opgehaald')
                        ->success()
                        ->send();
                }),
            $this->uploadImageAction(),
            GenerateImageAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function uploadImageAction(): Action
    {
        return Action::make('uploadImage')
            ->label('Upload afbeelding')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('primary')
            ->modalWidth('lg')
            ->modalHeading('Afbeelding(en) uploaden')
            ->modalSubmitActionLabel('Uploaden')
            ->schema([
                FileUpload::make('upload')
                    ->label('Afbeelding(en)')
                    ->multiple()
                    ->image()
                    ->disk('public')
                    ->directory('social-uploaded')
                    ->visibility('public')
                    ->required(),
            ])
            ->action(function (array $data): void {
                $record = $this->getRecord();
                $upload = $data['upload'] ?? [];
                if (! is_array($upload)) {
                    $upload = $upload ? [$upload] : [];
                }

                $existing = is_array($record->images) ? $record->images : [];
                $merged = array_values(array_filter(array_merge($existing, $upload)));

                $record->update([
                    'images' => $merged,
                    'image_path' => $record->image_path ?: ($merged[0] ?? null),
                ]);

                $this->refreshFormData(['images', 'image_path']);

                Notification::make()
                    ->title(count($upload).' afbeelding(en) toegevoegd')
                    ->success()
                    ->send();
            });
    }

    public function moveImage(int $from, int $to): void
    {
        $record = $this->getRecord();
        $images = is_array($record->images) ? array_values($record->images) : [];

        if (! isset($images[$from]) || $to < 0 || $to >= count($images)) {
            return;
        }

        $moved = array_splice($images, $from, 1);
        array_splice($images, $to, 0, $moved);

        $record->update([
            'images' => $images,
            'image_path' => $images[0] ?? null,
        ]);

        $this->refreshFormData(['images', 'image_path']);
    }

    public function deleteImage(int $index): void
    {
        $record = $this->getRecord();
        $images = is_array($record->images) ? array_values($record->images) : [];

        if (! isset($images[$index])) {
            return;
        }

        array_splice($images, $index, 1);

        $record->update([
            'images' => $images,
            'image_path' => $images[0] ?? null,
        ]);

        $this->refreshFormData(['images', 'image_path']);
    }

    protected function afterSave(): void
    {
        $record = $this->getRecord();
        $original = $record->getOriginal('caption');

        if ($original && $original !== $record->caption) {
            SocialPostVersion::create([
                'post_id' => $record->id,
                'caption' => $original,
                'created_by' => auth()->id(),
            ]);
        }
    }
}

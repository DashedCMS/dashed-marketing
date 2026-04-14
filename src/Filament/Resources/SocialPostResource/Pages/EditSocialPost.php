<?php

namespace Dashed\DashedMarketing\Filament\Resources\SocialPostResource\Pages;

use Dashed\DashedMarketing\Filament\Actions\GenerateImageAction;
use Dashed\DashedMarketing\Filament\Resources\SocialPostResource;
use Dashed\DashedMarketing\Models\SocialPost;
use Dashed\DashedMarketing\Models\SocialPostVersion;
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
            Action::make('sharePost')
                ->label('Deel post')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->modalHeading('Deel post')
                ->modalDescription('Kopieer de caption, download de afbeelding en open de post URL.')
                ->modalWidth('3xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Sluiten')
                ->modalContent(fn (SocialPost $record) => view(
                    'dashed-marketing::filament.modals.share-post',
                    ['record' => $record],
                )),
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

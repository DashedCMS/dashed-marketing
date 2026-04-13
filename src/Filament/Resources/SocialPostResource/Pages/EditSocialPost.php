<?php

namespace Dashed\DashedMarketing\Filament\Resources\SocialPostResource\Pages;

use Dashed\DashedMarketing\Filament\Actions\GenerateImageAction;
use Dashed\DashedMarketing\Filament\Resources\SocialPostResource;
use Dashed\DashedMarketing\Models\SocialPost;
use Dashed\DashedMarketing\Models\SocialPostVersion;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
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
            GenerateImageAction::make(),
            DeleteAction::make(),
        ];
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

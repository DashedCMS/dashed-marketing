<?php

namespace Dashed\DashedMarketing\Filament\Resources\SocialPostResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Dashed\DashedMarketing\Models\SocialPostVersion;
use Dashed\DashedMarketing\Filament\Resources\SocialPostResource;
use Dashed\DashedMarketing\Filament\Actions\GenerateImageAction;

class EditSocialPost extends EditRecord
{
    protected static string $resource = SocialPostResource::class;

    protected function getHeaderActions(): array
    {
        return [
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

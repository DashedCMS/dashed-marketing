<?php

namespace Dashed\DashedMarketing\Filament\Resources\ContentDraftResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Dashed\DashedMarketing\Filament\Resources\ContentDraftResource;

class EditContentDraft extends EditRecord
{
    protected static string $resource = ContentDraftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

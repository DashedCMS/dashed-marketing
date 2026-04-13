<?php

namespace Dashed\DashedMarketing\Filament\Resources\KeywordWorkspaceResource\Pages;

use Dashed\DashedMarketing\Filament\Resources\KeywordWorkspaceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditKeywordWorkspace extends EditRecord
{
    protected static string $resource = KeywordWorkspaceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

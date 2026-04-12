<?php

namespace Dashed\DashedMarketing\Filament\Resources\KeywordResearchResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Dashed\DashedMarketing\Filament\Resources\KeywordResearchResource;

class EditKeywordResearch extends EditRecord
{
    protected static string $resource = KeywordResearchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

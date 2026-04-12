<?php

namespace Dashed\DashedMarketing\Filament\Resources\KeywordResearchResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Dashed\DashedMarketing\Filament\Resources\KeywordResearchResource;

class ListKeywordResearches extends ListRecords
{
    protected static string $resource = KeywordResearchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

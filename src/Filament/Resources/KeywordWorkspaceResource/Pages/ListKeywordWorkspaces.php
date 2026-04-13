<?php

namespace Dashed\DashedMarketing\Filament\Resources\KeywordWorkspaceResource\Pages;

use Dashed\DashedMarketing\Filament\Resources\KeywordWorkspaceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListKeywordWorkspaces extends ListRecords
{
    protected static string $resource = KeywordWorkspaceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

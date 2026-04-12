<?php

namespace Dashed\DashedMarketing\Filament\Resources\ContentDraftResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Dashed\DashedMarketing\Filament\Resources\ContentDraftResource;

class ListContentDrafts extends ListRecords
{
    protected static string $resource = ContentDraftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

<?php

namespace Dashed\DashedMarketing\Filament\Resources\ContentClusterResource\Pages;

use Dashed\DashedMarketing\Filament\Resources\ContentClusterResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListContentClusters extends ListRecords
{
    protected static string $resource = ContentClusterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

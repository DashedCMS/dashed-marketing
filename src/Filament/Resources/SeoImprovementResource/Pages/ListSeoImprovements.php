<?php

namespace Dashed\DashedMarketing\Filament\Resources\SeoImprovementResource\Pages;

use Dashed\DashedMarketing\Filament\Resources\SeoImprovementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSeoImprovements extends ListRecords
{
    protected static string $resource = SeoImprovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

<?php

namespace Dashed\DashedMarketing\Filament\Resources\SeoImprovementResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Dashed\DashedMarketing\Filament\Resources\SeoImprovementResource;

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

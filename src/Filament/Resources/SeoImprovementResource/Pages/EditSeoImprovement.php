<?php

namespace Dashed\DashedMarketing\Filament\Resources\SeoImprovementResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Dashed\DashedMarketing\Filament\Resources\SeoImprovementResource;

class EditSeoImprovement extends EditRecord
{
    protected static string $resource = SeoImprovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

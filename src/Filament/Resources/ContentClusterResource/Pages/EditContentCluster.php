<?php

namespace Dashed\DashedMarketing\Filament\Resources\ContentClusterResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Dashed\DashedMarketing\Filament\Resources\ContentClusterResource;

class EditContentCluster extends EditRecord
{
    protected static string $resource = ContentClusterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

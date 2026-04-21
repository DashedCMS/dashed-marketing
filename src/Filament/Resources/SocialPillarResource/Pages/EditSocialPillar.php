<?php

namespace Dashed\DashedMarketing\Filament\Resources\SocialPillarResource\Pages;

use Dashed\DashedMarketing\Filament\Resources\SocialPillarResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSocialPillar extends EditRecord
{
    protected static string $resource = SocialPillarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

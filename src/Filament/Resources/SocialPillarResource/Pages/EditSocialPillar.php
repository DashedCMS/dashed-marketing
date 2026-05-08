<?php

namespace Dashed\DashedMarketing\Filament\Resources\SocialPillarResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Dashed\DashedMarketing\Filament\Resources\SocialPillarResource;

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

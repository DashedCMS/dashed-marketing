<?php

namespace Dashed\DashedMarketing\Filament\Resources\SocialChannelResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Dashed\DashedMarketing\Filament\Resources\SocialChannelResource;

class EditSocialChannel extends EditRecord
{
    protected static string $resource = SocialChannelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

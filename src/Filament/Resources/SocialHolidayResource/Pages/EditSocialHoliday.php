<?php

namespace Dashed\DashedMarketing\Filament\Resources\SocialHolidayResource\Pages;

use Dashed\DashedMarketing\Filament\Resources\SocialHolidayResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSocialHoliday extends EditRecord
{
    protected static string $resource = SocialHolidayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

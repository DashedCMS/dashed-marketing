<?php

namespace Dashed\DashedMarketing\Filament\Resources\SocialChannelResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Dashed\DashedMarketing\Filament\Resources\SocialChannelResource;

class ListSocialChannels extends ListRecords
{
    protected static string $resource = SocialChannelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

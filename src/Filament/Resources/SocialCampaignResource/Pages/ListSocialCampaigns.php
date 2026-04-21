<?php

namespace Dashed\DashedMarketing\Filament\Resources\SocialCampaignResource\Pages;

use Dashed\DashedMarketing\Filament\Resources\SocialCampaignResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSocialCampaigns extends ListRecords
{
    protected static string $resource = SocialCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

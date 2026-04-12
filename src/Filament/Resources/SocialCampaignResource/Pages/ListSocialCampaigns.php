<?php

namespace Dashed\DashedMarketing\Filament\Resources\SocialCampaignResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Dashed\DashedMarketing\Filament\Resources\SocialCampaignResource;

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

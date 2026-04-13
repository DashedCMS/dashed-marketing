<?php

namespace Dashed\DashedMarketing\Filament\Resources\SocialCampaignResource\Pages;

use Dashed\DashedMarketing\Filament\Resources\SocialCampaignResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSocialCampaign extends EditRecord
{
    protected static string $resource = SocialCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

<?php

namespace Dashed\DashedMarketing\Filament\Resources\SocialIdeaResource\Pages;

use Dashed\DashedMarketing\Filament\Resources\SocialIdeaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSocialIdea extends EditRecord
{
    protected static string $resource = SocialIdeaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

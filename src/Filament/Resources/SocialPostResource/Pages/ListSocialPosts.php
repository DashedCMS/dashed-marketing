<?php

namespace Dashed\DashedMarketing\Filament\Resources\SocialPostResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Dashed\DashedMarketing\Filament\Resources\SocialPostResource;
use Dashed\DashedMarketing\Filament\Actions\GeneratePostAction;

class ListSocialPosts extends ListRecords
{
    protected static string $resource = SocialPostResource::class;

    public function getSubheading(): ?string
    {
        return 'Beheer al je social media posts op één plek. Maak posts handmatig of laat AI varianten genereren. Posts doorlopen de workflow: concept → in review → goedgekeurd → ingepland → gepost.';
    }

    protected function getHeaderActions(): array
    {
        return [
            GeneratePostAction::make(),
            CreateAction::make(),
        ];
    }
}

<?php

namespace Dashed\DashedMarketing\Filament\Resources\SocialPillarResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Dashed\DashedMarketing\Filament\Resources\SocialPillarResource;

class ListSocialPillars extends ListRecords
{
    protected static string $resource = SocialPillarResource::class;

    public function getSubheading(): ?string
    {
        return 'Content pijlers bepalen de thematische verdeling van je social media content. Stel een streefpercentage in per pijler en monitor de werkelijke verdeling op het dashboard.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

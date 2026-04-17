<?php

namespace Dashed\DashedMarketing\Filament\Resources\SocialIdeaResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Dashed\DashedMarketing\Filament\Resources\SocialIdeaResource;
use Dashed\DashedMarketing\Filament\Actions\GenerateBulkIdeasAction;

class ListSocialIdeas extends ListRecords
{
    protected static string $resource = SocialIdeaResource::class;

    public function getSubheading(): ?string
    {
        return 'Verzamel ideeën voor social media content. Genereer ideeën met AI of voeg ze handmatig toe. Zet een idee met één klik om naar een volledige post.';
    }

    protected function getHeaderActions(): array
    {
        return [
            GenerateBulkIdeasAction::make(),
            CreateAction::make(),
        ];
    }
}

<?php

namespace Dashed\DashedMarketing\Filament\Resources\KeywordWorkspaceResource\Pages;

use Dashed\DashedMarketing\Filament\Resources\KeywordWorkspaceResource;
use Filament\Resources\Pages\Page;

class GenerateDrafts extends Page
{
    protected static string $resource = KeywordWorkspaceResource::class;

    protected string $view = 'dashed-marketing::filament.pages.generate-drafts';
}

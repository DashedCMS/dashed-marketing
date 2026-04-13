<?php

namespace Dashed\DashedMarketing\Filament\Resources\KeywordWorkspaceResource\Pages;

use Dashed\DashedMarketing\Filament\Resources\KeywordWorkspaceResource;
use Filament\Resources\Pages\Page;

class ImportKeywords extends Page
{
    protected static string $resource = KeywordWorkspaceResource::class;

    protected string $view = 'dashed-marketing::filament.pages.import-keywords';
}

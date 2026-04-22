<?php

namespace Dashed\DashedMarketing\Filament\Resources\SeoAuditResource\Pages;

use Dashed\DashedMarketing\Filament\Resources\SeoAuditResource;
use Dashed\DashedMarketing\Models\SeoAudit;
use Filament\Resources\Pages\Page;

class ReviewSeoAudit extends Page
{
    protected static string $resource = SeoAuditResource::class;

    protected string $view = 'dashed-marketing::filament.pages.review-seo-audit';

    public SeoAudit $record;

    public function mount(SeoAudit $record): void
    {
        $this->record = $record;
    }
}

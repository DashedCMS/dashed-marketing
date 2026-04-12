<?php

namespace Dashed\DashedMarketing;

use Filament\Panel;
use Filament\Contracts\Plugin;
use Dashed\DashedMarketing\Filament\Pages\SocialCalendarPage;
use Dashed\DashedMarketing\Filament\Pages\SocialDashboardPage;
use Dashed\DashedMarketing\Filament\Pages\Settings\SocialSettingsPage;
use Dashed\DashedMarketing\Filament\Resources\SocialPostResource;
use Dashed\DashedMarketing\Filament\Resources\SocialIdeaResource;
use Dashed\DashedMarketing\Filament\Resources\SocialPillarResource;
use Dashed\DashedMarketing\Filament\Resources\SocialHolidayResource;
use Dashed\DashedMarketing\Filament\Resources\SocialCampaignResource;
use Dashed\DashedMarketing\Filament\Resources\KeywordResearchResource;
use Dashed\DashedMarketing\Filament\Resources\ContentDraftResource;
use Dashed\DashedMarketing\Filament\Resources\ContentClusterResource;
use Dashed\DashedMarketing\Filament\Resources\SeoImprovementResource;

class DashedMarketingPlugin implements Plugin
{
    public function getId(): string
    {
        return 'dashed-marketing';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                SocialPostResource::class,
                SocialIdeaResource::class,
                SocialPillarResource::class,
                SocialHolidayResource::class,
                SocialCampaignResource::class,
                KeywordResearchResource::class,
                ContentDraftResource::class,
                ContentClusterResource::class,
                SeoImprovementResource::class,
            ])
            ->pages([
                SocialDashboardPage::class,
                SocialCalendarPage::class,
            ]);
    }

    public function boot(Panel $panel): void
    {
    }
}

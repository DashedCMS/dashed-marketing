<?php

namespace Dashed\DashedMarketing;

use Dashed\DashedMarketing\Filament\Pages\Settings\SocialSettingsPage;
use Dashed\DashedMarketing\Filament\Pages\SocialCalendarPage;
use Dashed\DashedMarketing\Filament\Pages\SocialDashboardPage;
use Dashed\DashedMarketing\Filament\Resources\ContentClusterResource;
use Dashed\DashedMarketing\Filament\Resources\ContentDraftResource;
use Dashed\DashedMarketing\Filament\Resources\KeywordResource;
use Dashed\DashedMarketing\Filament\Resources\SeoImprovementResource;
use Dashed\DashedMarketing\Filament\Resources\SocialCampaignResource;
use Dashed\DashedMarketing\Filament\Resources\SocialChannelResource;
use Dashed\DashedMarketing\Filament\Resources\SocialHolidayResource;
use Dashed\DashedMarketing\Filament\Resources\SocialIdeaResource;
use Dashed\DashedMarketing\Filament\Resources\SocialPillarResource;
use Dashed\DashedMarketing\Filament\Resources\SocialPostResource;
use Dashed\DashedMarketing\Filament\Widgets\SocialCalendarWidget;
use Filament\Contracts\Plugin;
use Filament\Panel;

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
                SocialChannelResource::class,
                SocialIdeaResource::class,
                SocialPillarResource::class,
                SocialHolidayResource::class,
                SocialCampaignResource::class,
                KeywordResource::class,
                ContentDraftResource::class,
                ContentClusterResource::class,
                SeoImprovementResource::class,
            ])
            ->pages([
                SocialDashboardPage::class,
                SocialCalendarPage::class,
                SocialSettingsPage::class,
            ])
            ->widgets([
                SocialCalendarWidget::class,
            ]);
    }

    public function boot(Panel $panel): void {}
}

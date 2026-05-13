<?php

namespace Dashed\DashedMarketing\Filament\Widgets;

use Dashed\DashedCore\Filament\Support\ResourceFilterUrl;
use Dashed\DashedMarketing\Filament\Resources\SocialPostResource;
use Dashed\DashedMarketing\Models\SocialPost;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Stat-widget bovenaan de social-posts-lijst: telt posts die in de
 * wachtrij staan. Klik leidt door naar de status-filter.
 */
class SocialPostPendingStat extends StatsOverviewWidget
{
    protected ?string $heading = null;

    protected function getStats(): array
    {
        $count = SocialPost::query()
            ->where('status', 'pending')
            ->count();

        return [
            Stat::make('Wachtende social posts', (string) $count)
                ->color('info')
                ->url(ResourceFilterUrl::for(SocialPostResource::class, [
                    'status' => 'pending',
                ])),
        ];
    }
}

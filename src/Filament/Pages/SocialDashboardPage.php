<?php

namespace Dashed\DashedMarketing\Filament\Pages;

use BackedEnum;
use Dashed\DashedMarketing\Models\SocialHoliday;
use Dashed\DashedMarketing\Models\SocialPillar;
use Dashed\DashedMarketing\Models\SocialPost;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use UnitEnum;

class SocialDashboardPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-bar';

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = 'Social dashboard';

    protected static ?string $title = 'Social dashboard';

    protected static ?int $navigationSort = 9;

    protected string $view = 'dashed-marketing::pages.social-dashboard';

    public function getWeekStats(): array
    {
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();

        $posts = SocialPost::whereBetween('scheduled_at', [$startOfWeek, $endOfWeek])
            ->orWhere(function ($q) use ($startOfWeek, $endOfWeek) {
                $q->whereBetween('posted_at', [$startOfWeek, $endOfWeek]);
            })->get();

        $allPosts = SocialPost::get();

        return [
            'posted' => SocialPost::where('status', 'posted')
                ->whereBetween('posted_at', [$startOfWeek, $endOfWeek])
                ->count(),
            'scheduled' => SocialPost::where('status', 'scheduled')
                ->whereBetween('scheduled_at', [$startOfWeek, $endOfWeek])
                ->count(),
            'overdue' => SocialPost::where('status', 'scheduled')
                ->where('scheduled_at', '<', now())
                ->count(),
            'concepts' => SocialPost::where('status', 'concept')->count(),
        ];
    }

    public function getPillarMix(): array
    {
        $pillars = SocialPillar::all();
        $totalPosts = SocialPost::where('status', 'posted')
            ->whereNotNull('pillar_id')
            ->count();

        $mix = [];
        foreach ($pillars as $pillar) {
            $count = SocialPost::where('pillar_id', $pillar->id)
                ->where('status', 'posted')
                ->count();

            $actual = $totalPosts > 0 ? round(($count / $totalPosts) * 100) : 0;

            $mix[] = [
                'name' => $pillar->name,
                'color' => $pillar->color ?? '#6b7280',
                'target' => $pillar->target_percentage,
                'actual' => $actual,
                'count' => $count,
            ];
        }

        return $mix;
    }

    public function getUpcomingHolidays(): array
    {
        return SocialHoliday::upcoming(30)->get()->map(fn ($h) => [
            'name' => $h->name,
            'date' => $h->date->format('d-m-Y'),
            'days_until' => now()->diffInDays($h->date, false),
            'country' => $h->country,
        ])->toArray();
    }
}

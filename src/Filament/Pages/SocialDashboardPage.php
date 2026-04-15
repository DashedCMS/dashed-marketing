<?php

namespace Dashed\DashedMarketing\Filament\Pages;

use BackedEnum;
use Dashed\DashedMarketing\Models\SocialHoliday;
use Dashed\DashedMarketing\Models\SocialIdea;
use Dashed\DashedMarketing\Models\SocialPillar;
use Dashed\DashedMarketing\Models\SocialPost;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
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
            'days_until' => (int) ceil(now()->startOfDay()->diffInDays($h->date->startOfDay(), false)),
            'country' => $h->country,
        ])->toArray();
    }

    public function getUpcomingPosts(): array
    {
        return SocialPost::query()
            ->whereIn('status', ['scheduled', 'approved'])
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '>=', now())
            ->orderBy('scheduled_at')
            ->limit(10)
            ->get()
            ->map(fn (SocialPost $p) => [
                'id' => $p->id,
                'caption' => Str::limit((string) $p->caption, 120),
                'channels' => is_array($p->channels) ? $p->channels : [],
                'scheduled_at' => optional($p->scheduled_at)->format('d-m-Y H:i'),
                'days_until' => (int) ceil(now()->startOfDay()->diffInDays(optional($p->scheduled_at)?->startOfDay() ?? now(), false)),
                'status' => $p->status,
                'has_image' => ! empty($p->images) || ! empty($p->image_path),
                'edit_url' => \Dashed\DashedMarketing\Filament\Resources\SocialPostResource::getUrl('edit', ['record' => $p->id]),
            ])
            ->toArray();
    }

    public function getOverduePosts(): array
    {
        return SocialPost::query()
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<', now())
            ->orderBy('scheduled_at')
            ->limit(10)
            ->get()
            ->map(fn (SocialPost $p) => [
                'id' => $p->id,
                'caption' => Str::limit((string) $p->caption, 120),
                'channels' => is_array($p->channels) ? $p->channels : [],
                'scheduled_at' => optional($p->scheduled_at)->format('d-m-Y H:i'),
                'edit_url' => \Dashed\DashedMarketing\Filament\Resources\SocialPostResource::getUrl('edit', ['record' => $p->id]),
            ])
            ->toArray();
    }

    public function getConceptsNeedingAttention(): array
    {
        return SocialPost::query()
            ->where('status', 'concept')
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get()
            ->map(fn (SocialPost $p) => [
                'id' => $p->id,
                'caption' => Str::limit((string) $p->caption, 120),
                'channels' => is_array($p->channels) ? $p->channels : [],
                'updated_at' => optional($p->updated_at)->diffForHumans(),
                'missing_image' => empty($p->images) && empty($p->image_path),
                'missing_schedule' => empty($p->scheduled_at),
                'edit_url' => \Dashed\DashedMarketing\Filament\Resources\SocialPostResource::getUrl('edit', ['record' => $p->id]),
            ])
            ->toArray();
    }

    public function getRecentlyPosted(): array
    {
        return SocialPost::query()
            ->where('status', 'posted')
            ->whereNotNull('posted_at')
            ->orderByDesc('posted_at')
            ->limit(5)
            ->get()
            ->map(fn (SocialPost $p) => [
                'id' => $p->id,
                'caption' => Str::limit((string) $p->caption, 120),
                'channels' => is_array($p->channels) ? $p->channels : [],
                'posted_at' => optional($p->posted_at)->format('d-m-Y H:i'),
                'post_url' => $p->post_url,
                'edit_url' => \Dashed\DashedMarketing\Filament\Resources\SocialPostResource::getUrl('edit', ['record' => $p->id]),
            ])
            ->toArray();
    }

    public function getChannelBreakdown(): array
    {
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        $counts = [];
        $posts = SocialPost::query()
            ->where(function ($q) use ($startOfMonth, $endOfMonth) {
                $q->whereBetween('scheduled_at', [$startOfMonth, $endOfMonth])
                    ->orWhereBetween('posted_at', [$startOfMonth, $endOfMonth]);
            })
            ->get();

        foreach ($posts as $post) {
            foreach ((array) $post->channels as $channel) {
                $counts[$channel] = ($counts[$channel] ?? 0) + 1;
            }
        }

        $channels = config('dashed-marketing.channels', []);
        $result = [];
        foreach ($counts as $key => $count) {
            $result[] = [
                'key' => $key,
                'label' => $channels[$key]['label'] ?? ucfirst($key),
                'count' => $count,
            ];
        }

        usort($result, fn ($a, $b) => $b['count'] <=> $a['count']);

        return $result;
    }

    public function getPendingIdeasCount(): int
    {
        if (! class_exists(SocialIdea::class)) {
            return 0;
        }

        return SocialIdea::query()
            ->whereIn('status', ['idea'])
            ->count();
    }
}

<?php

namespace Dashed\DashedMarketing\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Filament\Notifications\Notification;
use Dashed\DashedMarketing\Models\SocialPost;
use Dashed\DashedMarketing\Filament\Resources\SocialPostResource;

class SocialCalendarWidget extends Widget
{
    protected string $view = 'dashed-marketing::widgets.social-calendar';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $routeName = (string) (request()->route()?->getName() ?? '');

        if (str_contains($routeName, 'social-calendar')) {
            return true;
        }

        return SocialPost::withoutGlobalScopes()
            ->whereNotNull('scheduled_at')
            ->exists();
    }

    public int $currentYear;

    public int $currentMonth;

    public function mount(): void
    {
        $this->currentYear = now()->year;
        $this->currentMonth = now()->month;
    }

    public function previousMonth(): void
    {
        $date = Carbon::createFromDate($this->currentYear, $this->currentMonth, 1)->subMonth();
        $this->currentYear = $date->year;
        $this->currentMonth = $date->month;
    }

    public function nextMonth(): void
    {
        $date = Carbon::createFromDate($this->currentYear, $this->currentMonth, 1)->addMonth();
        $this->currentYear = $date->year;
        $this->currentMonth = $date->month;
    }

    public function reschedulePost(int $postId, string $newDate): void
    {
        $post = SocialPost::find($postId);

        if (! $post) {
            return;
        }

        $existingTime = $post->scheduled_at ? $post->scheduled_at->format('H:i:s') : '09:00:00';
        $post->update([
            'scheduled_at' => Carbon::parse($newDate.' '.$existingTime),
        ]);

        Notification::make()
            ->title('Post herscheduled')
            ->success()
            ->send();
    }

    public function getCalendarData(): array
    {
        $startOfMonth = Carbon::createFromDate($this->currentYear, $this->currentMonth, 1)->startOfMonth();
        $endOfMonth = $startOfMonth->copy()->endOfMonth();

        $posts = SocialPost::withoutGlobalScopes()
            ->whereNotNull('scheduled_at')
            ->whereBetween('scheduled_at', [$startOfMonth, $endOfMonth])
            ->with('pillar')
            ->get();

        $events = [];
        foreach ($posts as $post) {
            $events[] = [
                'id' => $post->id,
                'date' => $post->scheduled_at->format('Y-m-d'),
                'time' => $post->scheduled_at->format('H:i'),
                'platform' => $post->platform_label,
                'caption' => str($post->caption)->limit(40),
                'status' => $post->status,
                'color' => SocialPost::STATUS_COLORS[$post->status] ?? 'gray',
                'edit_url' => SocialPostResource::getUrl('edit', ['record' => $post->id]),
            ];
        }

        $weeks = [];
        $startOfCalendar = $startOfMonth->copy()->startOfWeek(Carbon::MONDAY);
        $endOfCalendar = $endOfMonth->copy()->endOfWeek(Carbon::SUNDAY);

        $current = $startOfCalendar->copy();
        while ($current <= $endOfCalendar) {
            $weekStart = $current->copy();
            $week = [];
            for ($d = 0; $d < 7; $d++) {
                $dateStr = $current->format('Y-m-d');
                $week[] = [
                    'date' => $dateStr,
                    'day' => $current->day,
                    'isCurrentMonth' => $current->month === $this->currentMonth,
                    'isToday' => $current->isToday(),
                    'events' => array_values(array_filter($events, fn ($e) => $e['date'] === $dateStr)),
                ];
                $current->addDay();
            }
            $weeks[] = $week;
        }

        return $weeks;
    }
}

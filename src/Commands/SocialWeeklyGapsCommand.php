<?php

namespace Dashed\DashedMarketing\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedMarketing\Models\SocialPost;
use Dashed\DashedMarketing\Mail\WeeklyGapsMail;
use Dashed\DashedMarketing\Jobs\SendSocialNotificationJob;

class SocialWeeklyGapsCommand extends Command
{
    protected $signature = 'social:weekly-gaps';

    protected $description = 'Send a notification email listing days without scheduled posts in the next 2 weeks.';

    public function handle(): void
    {
        foreach (Sites::getSites() as $site) {
            $siteId = $site['id'];

            $email = Customsetting::get('social_notification_email', $siteId);
            if (! $email || ! $this->isNotificationEnabled($siteId)) {
                continue;
            }

            $siteName = Customsetting::get('site_name', $siteId, 'Site');

            $emptyDates = $this->findEmptyDates($siteId);

            if ($emptyDates->isEmpty()) {
                $this->info("No gaps found for site {$siteId}.");

                continue;
            }

            SendSocialNotificationJob::dispatch(
                new WeeklyGapsMail($emptyDates, $siteName),
                $email,
                'weekly_gaps',
                $siteId,
            );

            $this->info("Dispatched weekly gaps notification for site {$siteId} ({$emptyDates->count()} empty days).");
        }
    }

    private function findEmptyDates(string $siteId): Collection
    {
        $start = today()->addDay();
        $end = today()->addDays(14);

        $scheduledDates = SocialPost::withoutGlobalScopes()
            ->where('site_id', $siteId)
            ->where('status', 'scheduled')
            ->whereBetween('scheduled_at', [$start, $end])
            ->pluck('scheduled_at')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->unique()
            ->values();

        $allDates = collect();
        $current = $start->copy();

        while ($current->lte($end)) {
            $allDates->push($current->toDateString());
            $current->addDay();
        }

        return $allDates->diff($scheduledDates)->values();
    }

    private function isNotificationEnabled(string $siteId): bool
    {
        return (bool) Customsetting::get('social_notifications_enabled', $siteId, true);
    }
}

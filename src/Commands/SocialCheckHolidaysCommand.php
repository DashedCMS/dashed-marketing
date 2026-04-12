<?php

namespace Dashed\DashedMarketing\Commands;

use Illuminate\Console\Command;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedMarketing\Jobs\SendSocialNotificationJob;
use Dashed\DashedMarketing\Mail\HolidayReminderMail;
use Dashed\DashedMarketing\Models\SocialHoliday;

class SocialCheckHolidaysCommand extends Command
{
    protected $signature = 'social:check-holidays';

    protected $description = 'Send reminder emails for upcoming holidays that need social media attention.';

    public function handle(): void
    {
        $holidays = SocialHoliday::needsReminder()->get();

        if ($holidays->isEmpty()) {
            $this->info('No holidays needing reminders today.');

            return;
        }

        foreach (Sites::getSites() as $site) {
            $siteId = $site['id'];

            $email = Customsetting::get('social_notification_email', $siteId);
            if (! $email || ! $this->isNotificationEnabled($siteId)) {
                continue;
            }

            $siteName = Customsetting::get('site_name', $siteId, 'Site');

            foreach ($holidays as $holiday) {
                SendSocialNotificationJob::dispatch(
                    new HolidayReminderMail($holiday, $siteName),
                    $email,
                    'holiday_reminder',
                    $siteId,
                );
            }

            $this->info("Dispatched holiday reminders for site {$siteId} ({$holidays->count()} holidays).");
        }
    }

    private function isNotificationEnabled(string $siteId): bool
    {
        return (bool) Customsetting::get('social_notifications_enabled', $siteId, true);
    }
}

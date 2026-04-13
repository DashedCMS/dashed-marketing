<?php

namespace Dashed\DashedMarketing\Commands;

use Illuminate\Console\Command;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedMarketing\Models\SocialPost;
use Dashed\DashedMarketing\Mail\PostsDueTodayMail;
use Dashed\DashedMarketing\Jobs\SendSocialNotificationJob;

class SocialNotifyDueCommand extends Command
{
    protected $signature = 'social:notify-due';

    protected $description = 'Send a notification email for social posts due today.';

    public function handle(): void
    {
        foreach (Sites::getSites() as $site) {
            $siteId = $site['id'];

            $email = Customsetting::get('social_notification_email', $siteId);
            if (! $email || ! $this->isNotificationEnabled($siteId)) {
                continue;
            }

            $siteName = Customsetting::get('site_name', $siteId, 'Site');

            $posts = SocialPost::withoutGlobalScopes()
                ->where('site_id', $siteId)
                ->where('status', 'scheduled')
                ->whereDate('scheduled_at', today())
                ->get();

            if ($posts->isEmpty()) {
                continue;
            }

            SendSocialNotificationJob::dispatch(
                new PostsDueTodayMail($posts, $siteName),
                $email,
                'posts_due_today',
                $siteId,
            );

            $this->info("Dispatched due notification for site {$siteId} ({$posts->count()} posts).");
        }
    }

    private function isNotificationEnabled(string $siteId): bool
    {
        return (bool) Customsetting::get('social_notifications_enabled', $siteId, true);
    }
}

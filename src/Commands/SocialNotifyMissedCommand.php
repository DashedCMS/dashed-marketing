<?php

namespace Dashed\DashedMarketing\Commands;

use Illuminate\Console\Command;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedMarketing\Models\SocialPost;
use Dashed\DashedMarketing\Mail\PostMissedMail;
use Dashed\DashedMarketing\Jobs\SendSocialNotificationJob;

class SocialNotifyMissedCommand extends Command
{
    protected $signature = 'social:notify-missed';

    protected $description = 'Send a notification email for social posts from yesterday that were not posted.';

    public function handle(): void
    {
        foreach (Sites::getSites() as $site) {
            $siteId = $site['id'];

            $email = Customsetting::get('social_notification_email', $siteId);
            if (! $email || ! $this->isNotificationEnabled($siteId)) {
                continue;
            }

            $siteName = Customsetting::get('site_name', $siteId, 'Site');

            $missedPosts = SocialPost::withoutGlobalScopes()
                ->where('site_id', $siteId)
                ->where('status', 'scheduled')
                ->whereDate('scheduled_at', today()->subDay())
                ->get();

            foreach ($missedPosts as $post) {
                SendSocialNotificationJob::dispatch(
                    new PostMissedMail($post, $siteName),
                    $email,
                    'post_missed',
                    $siteId,
                );
            }

            if ($missedPosts->isNotEmpty()) {
                $this->info("Dispatched missed notifications for site {$siteId} ({$missedPosts->count()} posts).");
            }
        }
    }

    private function isNotificationEnabled(string $siteId): bool
    {
        return (bool) Customsetting::get('social_notifications_enabled', $siteId, true);
    }
}

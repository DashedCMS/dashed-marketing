<?php

namespace Dashed\DashedMarketing\Jobs;

use Illuminate\Bus\Queueable;
use Filament\Actions\Action;
use Dashed\DashedCore\Classes\Mails;
use Dashed\DashedCore\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Queue\InteractsWithQueue;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedMarketing\Models\SocialPost;
use Dashed\DashedMarketing\Filament\Resources\SocialPostResource;
use Dashed\DashedMarketing\Services\AutoDrafts\AutoSocialDrafts;

/**
 * Maakt het automatische concept via dezelfde flow als de knop "Genereer post
 * met AI" (GenerateSocialPostJob), hangt de afbeelding van het item eraan en
 * meldt het aan de beheerders. Publiceert nooit.
 */
class GenerateAutoSocialDraftJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        public string $type,
        public string $subjectClass,
        public int|string $subjectId,
        public string $siteId,
    ) {
    }

    public function handle(): void
    {
        if (! class_exists($this->subjectClass)) {
            return;
        }

        $subject = $this->subjectClass::query()->find($this->subjectId);

        if (! $subject || AutoSocialDrafts::alreadyHasPost($subject)) {
            return;
        }

        $channels = AutoSocialDrafts::channels($this->siteId);

        if ($channels === []) {
            return;
        }

        $generator = new GenerateSocialPostJob(
            type: 'post',
            channels: $channels,
            subject: $subject,
            pillarId: null,
            campaignId: null,
            toneOverride: null,
            extraInstructions: __('Dit is een aankondiging: het onderwerp is net nieuw op de website verschenen.'),
            includeKeywords: false,
            scheduledAt: null,
            siteId: $this->siteId,
        );
        $generator->handle();

        $post = $generator->createdPostId
            ? SocialPost::query()->withoutGlobalScopes()->find($generator->createdPostId)
            : null;

        if (! $post) {
            return;
        }

        $image = $this->subjectImageUrl($subject);
        $post->forceFill([
            // Altijd een concept, ook als de generator ooit iets anders zou zetten.
            'status' => 'concept',
            'scheduled_at' => null,
            'images' => $image ? [$image] : $post->images,
            'image_path' => $image ?: $post->image_path,
            'external_data' => array_merge((array) $post->external_data, [
                'auto_draft' => ['type' => $this->type, 'created_at' => now()->toIso8601String()],
            ]),
        ])->save();

        $this->notifyAdmins($post, $subject);
    }

    protected function subjectImageUrl(object $subject): ?string
    {
        return rescue(function () use ($subject): ?string {
            $mediaId = $subject->firstImage ?? null;

            if (! $mediaId) {
                $mediaId = $subject->image ?? null;
                $mediaId = is_array($mediaId) ? ($mediaId[app()->getLocale()] ?? reset($mediaId) ?: null) : $mediaId;
            }

            if (! $mediaId || ! function_exists('mediaHelper')) {
                return null;
            }

            $media = mediaHelper()->getSingleMedia($mediaId);
            $url = is_object($media) ? ($media->url ?? null) : (is_string($media) && $media !== '' ? $media : null);

            return $url && preg_match('#^https?://#i', $url) ? $url : ($url ? url($url) : null);
        }, null, false);
    }

    protected function notifyAdmins(SocialPost $post, object $subject): void
    {
        $name = (string) ($subject->name ?? $subject->title ?? '#' . $subject->getKey());
        $title = __('Nieuw concept voor social media: :naam', ['naam' => $name]);
        $body = __('Er is automatisch een concept social post gemaakt omdat ":naam" net gepubliceerd is. Hij wordt niet vanzelf geplaatst: lees hem na, pas hem aan en plan hem zelf in.', ['naam' => $name]);
        $url = rescue(fn () => SocialPostResource::getUrl('edit', ['record' => $post]), null, false);

        foreach (User::query()->whereIn('role', ['admin', 'superadmin'])->get() as $admin) {
            rescue(fn () => Notification::make()
                ->title($title)
                ->body($body)
                ->info()
                ->actions($url ? [Action::make('view')->label(__('Concept bekijken'))->url($url)] : [])
                ->sendToDatabase($admin), report: false);
        }

        foreach (Mails::getAdminNotificationEmails() as $email) {
            rescue(fn () => Mail::to($email)->send(new \Dashed\DashedCore\Mail\NotificationMail($body, $title)), report: false);
        }
    }
}

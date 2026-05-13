<?php

namespace Dashed\DashedMarketing\Jobs;

use Throwable;
use Illuminate\Bus\Queueable;
use Dashed\DashedCore\Models\User;
use Illuminate\Support\Facades\Log;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Queue\SerializesModels;
use Filament\Notifications\Notification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedMarketing\Models\SocialIdea;
use Dashed\DashedMarketing\Models\SocialChannel;

class GenerateBulkPostsFromIdeasJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public array $ideaIds,
        public ?int $userId = null,
    ) {
    }

    public function handle(): void
    {
        $ideas = SocialIdea::withoutGlobalScopes()
            ->whereIn('id', $this->ideaIds)
            ->get();

        $allActiveChannels = SocialChannel::query()
            ->where('is_active', true)
            ->orderBy('order')
            ->get();

        $dispatched = 0;
        foreach ($ideas as $idea) {
            $type = $idea->type ?: 'post';
            $channels = is_array($idea->channels) && ! empty($idea->channels)
                ? $idea->channels
                : $allActiveChannels
                    ->filter(fn (SocialChannel $ch) => in_array($type, $ch->accepted_types ?? [], true))
                    ->pluck('slug')
                    ->all();

            if (empty($channels)) {
                continue;
            }

            $extraInstructions = trim(
                'Gebruik dit idee als basis:'
                ."\nTitel: ".($idea->title ?? '')
                .($idea->notes ? "\nNotities: ".$idea->notes : '')
                .(! empty($idea->tags) ? "\nTags: ".implode(', ', (array) $idea->tags) : '')
            );

            GenerateSocialPostJob::dispatch(
                type: $type,
                channels: $channels,
                subject: $idea->subject,
                pillarId: $idea->pillar_id,
                campaignId: null,
                toneOverride: null,
                extraInstructions: $extraInstructions,
                includeKeywords: false,
                scheduledAt: null,
                siteId: $idea->site_id ?: Sites::getActive(),
            );

            $idea->update(['status' => 'in_production']);

            $dispatched++;
        }

        if ($this->userId !== null) {
            $user = User::find($this->userId);
            if ($user !== null) {
                Notification::make()
                    ->title("{$dispatched} posts staan in de wachtrij")
                    ->body('De posts worden op de achtergrond gegenereerd via AI.')
                    ->icon('heroicon-o-paper-airplane')
                    ->success()
                    ->sendToDatabase($user);
            }
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error('GenerateBulkPostsFromIdeasJob failed', [
            'error' => $e->getMessage(),
            'idea_ids' => $this->ideaIds,
        ]);

        if ($this->userId !== null) {
            $user = User::find($this->userId);
            if ($user !== null) {
                Notification::make()
                    ->title('Bulk genereren mislukt')
                    ->body('De bulk-job kon niet worden voltooid. Check de logs.')
                    ->danger()
                    ->sendToDatabase($user);
            }
        }
    }
}

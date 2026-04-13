<?php

namespace Dashed\DashedMarketing\Jobs;

use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Models\User;
use Dashed\DashedMarketing\Models\SocialIdea;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateBulkPostsFromIdeasJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public array $ideaIds,
        public ?int $userId = null,
    ) {}

    public function handle(): void
    {
        $ideas = SocialIdea::withoutGlobalScopes()
            ->whereIn('id', $this->ideaIds)
            ->get();

        $dispatched = 0;
        foreach ($ideas as $idea) {
            $platform = $idea->platform ?: array_key_first(config('dashed-marketing.platforms', []) ?? []);

            if (! $platform) {
                continue;
            }

            $extraInstructions = trim(
                'Gebruik dit idee als basis:'
                ."\nTitel: ".($idea->title ?? '')
                .($idea->notes ? "\nNotities: ".$idea->notes : '')
                .(! empty($idea->tags) ? "\nTags: ".implode(', ', (array) $idea->tags) : '')
            );

            GenerateSocialPostJob::dispatch(
                platform: $platform,
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

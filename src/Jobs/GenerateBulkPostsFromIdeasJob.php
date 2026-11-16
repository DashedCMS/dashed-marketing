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
use Dashed\DashedMarketing\Support\MarketingTask;

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

        // Eén taak in het takenicoon van dashed-core voor de hele selectie;
        // elke post-job telt er één bij op.
        $taskKey = MarketingTask::jobKey($this) ?? MarketingTask::key();
        MarketingTask::start(
            GenerateSocialPostJob::TASK_TYPE,
            __('Social posts genereren van ideeën'),
            $taskKey,
            $ideas->count(),
            $this->userId,
            notify: true,
        );

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
                taskKey: $taskKey,
            );

            $idea->update(['status' => 'in_production']);

            $dispatched++;
        }

        // Ideeën zonder kanaal zijn overgeslagen: het totaal is wat echt is gestart.
        MarketingTask::total(
            GenerateSocialPostJob::TASK_TYPE,
            $taskKey,
            $dispatched,
            $dispatched === 0 ? __('Geen posts gestart: de ideeën hebben geen passend kanaal.') : __('De posts staan als concept klaar.'),
        );

        if ($this->userId !== null) {
            $user = User::find($this->userId);
            if ($user !== null) {
                Notification::make()
                    ->title(__(':aantal posts staan in de wachtrij', ['aantal' => $dispatched]))
                    ->body(__('De posts worden op de achtergrond gegenereerd via AI.'))
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

        MarketingTask::fail(GenerateSocialPostJob::TASK_TYPE, MarketingTask::jobKey($this), $e);

        if ($this->userId !== null) {
            $user = User::find($this->userId);
            if ($user !== null) {
                Notification::make()
                    ->title(__('Bulk genereren mislukt'))
                    ->body(__('De bulk-job kon niet worden voltooid. Check de logs.'))
                    ->danger()
                    ->sendToDatabase($user);
            }
        }
    }
}

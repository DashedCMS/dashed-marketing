<?php

namespace Dashed\DashedMarketing\Jobs;

use Dashed\DashedAi\Facades\Ai;
use Dashed\DashedCore\Models\User;
use Dashed\DashedMarketing\Models\SocialIdea;
use Dashed\DashedMarketing\Services\SocialContextBuilder;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateBulkSocialIdeasJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $period,
        public int $count,
        public ?string $focus = null,
        public ?int $userId = null,
    ) {}

    public function handle(): void
    {
        try {
            $contextBuilder = new SocialContextBuilder;
            $context = $contextBuilder->build();

            $period = $this->period;
            $count = $this->count;
            $focus = $this->focus;
            $focusLine = $focus ? "Focus voor deze periode: {$focus}\n" : '';

            $prompt = <<<PROMPT
            {$context}

            {$focusLine}
            Genereer {$count} concrete social media ideeën voor de komende {$period} week(en).
            Varieer in platform, content pijler, en type (tips, behind-the-scenes, product, inspiratie, humor).

            Retourneer UITSLUITEND geldig JSON in dit formaat:
            {
                "ideas": [
                    {
                        "title": "Korte beschrijvende titel",
                        "platform": "instagram_feed",
                        "notes": "Korte toelichting op het idee en aanpak",
                        "tags": ["tag1", "tag2"]
                    }
                ]
            }
            PROMPT;

            $result = Ai::json($prompt);

            if (! $result || empty($result['ideas'])) {
                Log::warning('GenerateBulkSocialIdeasJob: AI provider returned no usable response.', [
                    'period' => $period,
                    'count' => $count,
                    'focus' => $focus,
                ]);

                if ($this->userId !== null) {
                    $user = User::find($this->userId);
                    if ($user !== null) {
                        Notification::make()
                            ->title('Geen ideeën gegenereerd')
                            ->body('De AI leverde geen geldige suggesties. Probeer een specifiekere focus.')
                            ->warning()
                            ->sendToDatabase($user);
                    }
                }

                return;
            }

            $created = 0;
            foreach ($result['ideas'] as $idea) {
                SocialIdea::create([
                    'title' => $idea['title'] ?? 'Onbekend idee',
                    'platform' => $idea['platform'] ?? null,
                    'notes' => $idea['notes'] ?? null,
                    'tags' => $idea['tags'] ?? [],
                    'status' => 'idea',
                ]);
                $created++;
            }

            if ($this->userId !== null) {
                $user = User::find($this->userId);
                if ($user !== null) {
                    Notification::make()
                        ->title("{$created} social media ideeën aangemaakt")
                        ->body($this->focus ? "Focus: {$this->focus}" : "Periode: {$this->period} weken")
                        ->icon('heroicon-o-sparkles')
                        ->success()
                        ->sendToDatabase($user);
                }
            }
        } catch (Throwable $e) {
            Log::warning('GenerateBulkSocialIdeasJob failed: '.$e->getMessage(), [
                'exception' => $e,
            ]);

            throw $e;
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error('GenerateBulkSocialIdeasJob failed', [
            'error' => $e->getMessage(),
            'period' => $this->period,
            'count' => $this->count,
            'focus' => $this->focus,
        ]);

        if ($this->userId !== null) {
            $user = User::find($this->userId);
            if ($user !== null) {
                Notification::make()
                    ->title('Genereren mislukt')
                    ->body('De AI-job kon niet worden voltooid. Check de logs.')
                    ->danger()
                    ->sendToDatabase($user);
            }
        }
    }
}

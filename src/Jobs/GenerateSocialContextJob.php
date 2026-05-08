<?php

namespace Dashed\DashedMarketing\Jobs;

use Illuminate\Bus\Queueable;
use Dashed\DashedAi\Facades\Ai;
use Dashed\DashedCore\Models\User;
use Illuminate\Queue\SerializesModels;
use Filament\Notifications\Notification;
use Illuminate\Queue\InteractsWithQueue;
use Dashed\DashedCore\Models\Customsetting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedMarketing\Services\SocialContextSourceCollector;

class GenerateSocialContextJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $backoff = 30;

    public int $timeout = 120;

    public function __construct(
        public int|string $siteId,
        public int $userId,
    ) {
    }

    public function handle(): void
    {
        $collector = new SocialContextSourceCollector();
        $context = $collector->collect($this->siteId);

        $response = Ai::json($this->buildPrompt($context));

        if (! is_array($response)) {
            $this->notifyUser(
                'AI generatie mislukt',
                "Geen geldig antwoord van de AI voor site '{$this->siteId}'.",
                'danger'
            );

            return;
        }

        $updated = [];

        if ($this->writeIfEmpty('social_target_audience', $response['target_audience'] ?? null)) {
            $updated[] = 'doelgroep';
        }

        if ($this->writeIfEmpty('social_usps', $response['usps'] ?? null)) {
            $updated[] = 'USPs';
        }

        if (empty($updated)) {
            $body = "Alle velden voor site '{$this->siteId}' waren al ingevuld - niets gewijzigd.";
        } else {
            $body = "Bijgewerkte velden voor site '{$this->siteId}': ".implode(', ', $updated).'.';
        }

        $this->notifyUser('AI context generatie klaar', $body, 'success');
    }

    protected function writeIfEmpty(string $key, mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value) && trim($value) === '') {
            return false;
        }

        $current = Customsetting::get($key, (string) $this->siteId);

        if ($current !== null && $current !== '') {
            return false;
        }

        Customsetting::set($key, $value, (string) $this->siteId);

        return true;
    }

    protected function notifyUser(string $title, string $body, string $type): void
    {
        $user = User::find($this->userId);

        if (! $user) {
            return;
        }

        $notification = Notification::make()
            ->title($title)
            ->body($body);

        if ($type === 'danger') {
            $notification->danger();
        } else {
            $notification->success();
        }

        $notification->sendToDatabase($user);
    }

    protected function buildPrompt(string $context): string
    {
        return <<<PROMPT
Je bent een marketing strateeg. Op basis van de onderstaande website
content moet je twee dingen genereren voor de social media context van
deze website:

1. "target_audience" - een korte beschrijving (max 3 zinnen) van de
   primaire doelgroep van dit bedrijf.
2. "usps" - een korte opsomming (max 5 bullet points, in tekstvorm) van
   de belangrijkste unique selling points van dit bedrijf.

Schrijf in het Nederlands. Antwoord uitsluitend met een geldig JSON
object van de vorm:

{
  "target_audience": "...",
  "usps": "- ...\\n- ..."
}

Website content:
---
{$context}
---
PROMPT;
    }
}

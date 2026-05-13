<?php

namespace Dashed\DashedMarketing\Jobs;

use Dashed\DashedAi\Facades\Ai;
use Dashed\DashedMarketing\Models\ContentDraft;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateDraftMetaJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 15;

    public int $timeout = 60;

    public function __construct(public int $draftId, public bool $overwrite = false) {}

    public function handle(): void
    {
        $draft = ContentDraft::with(['keywords', 'sections'])->find($this->draftId);
        if ($draft === null) {
            return;
        }

        if (! $this->overwrite && ! empty($draft->meta_title) && ! empty($draft->meta_description)) {
            return;
        }

        $response = Ai::json($this->buildPrompt($draft));

        if ($response === null) {
            throw new \RuntimeException('Ai::json returned null');
        }

        $title = $this->extractTitle($response);
        $description = $this->extractDescription($response);

        if ($title === '' && $description === '') {
            throw new \RuntimeException('AI returned no meta fields. Keys: '.implode(', ', array_keys($response)));
        }

        $updates = [];

        if ($title !== '' && ($this->overwrite || empty($draft->meta_title))) {
            $updates['meta_title'] = mb_substr($title, 0, 150);
        }

        if ($description !== '' && ($this->overwrite || empty($draft->meta_description))) {
            $updates['meta_description'] = mb_substr($description, 0, 250);
        }

        if (! empty($updates)) {
            $draft->update($updates);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('GenerateDraftMetaJob: final failure after retries', [
            'draft_id' => $this->draftId,
            'error' => $exception->getMessage(),
        ]);
    }

    private function extractTitle(array $response): string
    {
        foreach (['meta_title', 'title', 'seo_title'] as $key) {
            if (! empty($response[$key]) && is_string($response[$key])) {
                return trim($response[$key]);
            }
        }

        return '';
    }

    private function extractDescription(array $response): string
    {
        foreach (['meta_description', 'description', 'seo_description'] as $key) {
            if (! empty($response[$key]) && is_string($response[$key])) {
                return trim($response[$key]);
            }
        }

        return '';
    }

    protected function buildPrompt(ContentDraft $draft): string
    {
        $keywords = $draft->keywords
            ->map(fn ($k) => "- {$k->keyword}")
            ->implode("\n");

        $outline = $draft->sections
            ->map(fn ($s) => '- '.($s->heading ?? ''))
            ->implode("\n");

        $instruction = $draft->instruction ? "Instructie: {$draft->instruction}\n" : '';

        return <<<TXT
Schrijf een SEO meta title en meta description voor het artikel "{$draft->name}" (taal: {$draft->locale}).

Outline van het artikel:
{$outline}

Keywords (primair belangrijk):
{$keywords}

{$instruction}

Regels (hard):
- meta_title: 50 tot 60 tekens, bevat het primaire keyword, pakkend en uniek, GEEN merknaam tenzij het aantoonbaar toegevoegde waarde heeft.
- meta_description: 140 tot 160 tekens, beschrijft concreet wat de lezer krijgt, eindigt met een subtiele call-to-action, bevat minimaal 1 keyword natuurlijk verwerkt.
- Nederlandse of doeltaal-spelling, geen emoji, geen quotes in de output, geen uitroepteken-spam.
- Geen generieke zinnen als "Lees meer" of "Klik hier".

Retourneer JSON: {"meta_title": "...", "meta_description": "..."}
TXT;
    }
}

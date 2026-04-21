<?php

namespace Dashed\DashedMarketing\Jobs;

use Dashed\DashedAi\Facades\Ai;
use Dashed\DashedMarketing\Models\ContentDraft;
use Dashed\DashedMarketing\Models\ContentDraftFaq;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateDraftFaqsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 15;

    public int $timeout = 60;

    public function __construct(public int $draftId) {}

    public function handle(): void
    {
        $draft = ContentDraft::with(['keywords', 'sections'])->find($this->draftId);
        if ($draft === null) {
            return;
        }

        $response = Ai::json($this->buildPrompt($draft));

        if ($response === null) {
            throw new \RuntimeException('Ai::json returned null');
        }

        $faqs = $this->extractFaqs($response);

        if (empty($faqs)) {
            throw new \RuntimeException('AI returned no FAQs. Keys: '.implode(', ', array_keys($response)));
        }

        $draft->faqs()->delete();
        foreach ($faqs as $index => $faq) {
            ContentDraftFaq::create([
                'content_draft_id' => $draft->id,
                'sort_order' => $index,
                'question' => $faq['question'],
                'answer' => $faq['answer'],
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('GenerateDraftFaqsJob: final failure after retries', [
            'draft_id' => $this->draftId,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * @return array<int, array{question: string, answer: string}>
     */
    private function extractFaqs(array $response): array
    {
        $raw = $response['faqs'] ?? $response['questions'] ?? [];
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $item) {
            if (! is_array($item)) {
                continue;
            }
            $q = trim((string) ($item['question'] ?? $item['q'] ?? ''));
            $a = trim((string) ($item['answer'] ?? $item['a'] ?? ''));
            if ($q === '' || $a === '') {
                continue;
            }
            $out[] = ['question' => $q, 'answer' => $a];
        }

        return $out;
    }

    protected function buildPrompt(ContentDraft $draft): string
    {
        $keywords = $draft->keywords->map(fn ($k) => "- {$k->keyword}")->implode("\n");
        $outline = $draft->sections
            ->map(fn ($s) => '- '.($s->heading ?? ''))
            ->implode("\n");

        $instruction = $draft->instruction ? "Instructie: {$draft->instruction}\n" : '';

        return <<<TXT
Bedenk 5 tot 8 relevante FAQs voor het artikel "{$draft->name}" (taal: {$draft->locale}).

Outline van het artikel:
{$outline}

Keywords:
{$keywords}

{$instruction}

Regels:
- Elke FAQ is een realistische vraag die een bezoeker stelt over dit onderwerp.
- Het antwoord is beknopt, concreet, 1 tot 3 zinnen, geen verkooppraatjes.
- Geen vragen die letterlijk al in de H2-outline staan.

Retourneer JSON: {"faqs": [{"question": "...", "answer": "..."}, ...]}
TXT;
    }
}

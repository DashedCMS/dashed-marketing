<?php

namespace Dashed\DashedMarketing\Jobs;

use Dashed\DashedAi\Facades\Ai;
use Dashed\DashedMarketing\Models\ContentDraft;
use Dashed\DashedMarketing\Models\ContentDraftSection;
use Dashed\DashedMarketing\Services\LinkCandidatesService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateSectionBodyJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 15;

    public int $timeout = 60;

    public function __construct(public int $sectionId) {}

    public function handle(LinkCandidatesService $links): void
    {
        $section = ContentDraftSection::with('contentDraft.keywords')->find($this->sectionId);
        if ($section === null) {
            return;
        }

        $draft = $section->contentDraft;
        if ($draft === null) {
            return;
        }

        $candidates = $this->resolveCandidates($draft, $links);
        $allSections = $draft->sections()->orderBy('sort_order')->get();

        $response = Ai::json($this->buildPrompt($draft, $section, $allSections, $candidates));

        if ($response === null) {
            throw new \RuntimeException('Ai::json returned null');
        }

        $body = $this->extractBody($response);

        if ($body === '') {
            throw new \RuntimeException('AI returned no body. Keys: '.implode(', ', array_keys($response)));
        }

        $section->update([
            'body' => $body,
            'error_message' => null,
        ]);

        $this->maybeUpdateDraftStatus($draft);
    }

    public function failed(\Throwable $exception): void
    {
        $section = ContentDraftSection::find($this->sectionId);
        if ($section === null) {
            return;
        }

        Log::error('GenerateSectionBodyJob: final failure after retries', [
            'section_id' => $section->id,
            'draft_id' => $section->content_draft_id,
            'error' => $exception->getMessage(),
        ]);

        $section->update([
            'error_message' => 'AI-generatie gefaald na '.$this->tries.' pogingen: '.$exception->getMessage(),
        ]);

        if ($section->contentDraft) {
            $this->maybeUpdateDraftStatus($section->contentDraft);
        }
    }

    /**
     * Prefer the draft's curated link candidates (user-edited). Fall back to
     * the route-model service only when the draft has none set yet.
     *
     * @return array<int, array{type: string, title: string, url: string}>
     */
    private function resolveCandidates(ContentDraft $draft, LinkCandidatesService $links): array
    {
        $drafted = $draft->linkCandidates()->orderBy('sort_order')->get();

        if ($drafted->isNotEmpty()) {
            return $drafted
                ->map(fn ($c) => [
                    'type' => (string) ($c->type ?? ''),
                    'title' => (string) $c->title,
                    'url' => (string) $c->url,
                ])
                ->values()
                ->all();
        }

        return $links->forLocale($draft->locale ?? 'nl', 20);
    }

    private function extractBody(array $response): string
    {
        foreach (['body', 'html', 'content', 'text'] as $key) {
            if (! empty($response[$key]) && is_string($response[$key])) {
                return trim($response[$key]);
            }
        }

        return '';
    }

    private function maybeUpdateDraftStatus(ContentDraft $draft): void
    {
        $sections = $draft->sections()->get();
        if ($sections->isEmpty()) {
            return;
        }

        $withBody = $sections->filter(fn ($s) => ! empty($s->body))->count();
        $withError = $sections->filter(fn ($s) => empty($s->body) && ! empty($s->error_message))->count();
        $processed = $withBody + $withError;

        if ($processed < $sections->count()) {
            return;
        }

        $newStatus = $withBody > 0 ? 'ready' : 'failed';
        if ($draft->status !== $newStatus) {
            $draft->update(['status' => $newStatus]);
        }
    }

    protected function buildPrompt(ContentDraft $draft, ContentDraftSection $current, $allSections, array $candidates): string
    {
        $keywords = $draft->keywords->map(function ($k) {
            $volume = $k->volume_exact ? "volume {$k->volume_exact}" : "volume {$k->volume_indication}";

            return "- {$k->keyword} ({$k->type}, {$volume})";
        })->implode("\n");

        $outline = $allSections
            ->map(fn ($s) => ($s->id === $current->id ? '>> ' : '   ').($s->heading ?? ''))
            ->implode("\n");

        $candidatesJson = json_encode(
            array_map(fn ($c) => ['title' => $c['title'], 'url' => $c['url']], $candidates),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        return <<<TXT
Schrijf HTML body voor de gemarkeerde (>>) sectie van artikel "{$draft->name}" (taal: {$draft->locale}).

Outline (>> is de te schrijven sectie):
{$outline}

Huidige sectie:
- heading: {$current->heading}
- intent: {$current->intent}

Keywords om thematisch te gebruiken (primaire keywords zwaarder):
{$keywords}

Interne link-kandidaten (gebruik 1 tot 3 die thematisch passen):
{$candidatesJson}

Regels (hard):
- Retourneer HTML-string in veld "body".
- Alleen deze tags: p, ul, ol, li, strong, em, a.
- Geen img, geen script, geen style, geen heading tags (h1-h6).
- 1 tot 3 interne links als <a href='...'>anchor</a> naar URLs uit de link-kandidaten. Gebruik alleen URLs die exact in de lijst staan.
- Laat andere secties ongemoeid, schrijf alleen deze sectie.

Retourneer JSON: {"body": "<p>...</p>"}
TXT;
    }
}

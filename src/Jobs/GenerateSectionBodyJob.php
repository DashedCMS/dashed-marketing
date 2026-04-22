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

        $allSections = $draft->sections()->orderBy('sort_order')->get();
        $candidates = $this->resolveCandidates($draft, $links);
        $candidates = $this->filterAlreadyUsed($candidates, $allSections, $section->id);

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
                ->map(function ($c) {
                    $title = (string) $c->title;
                    $url = (string) $c->url;
                    $type = (string) ($c->type ?? '');

                    // If linked to a live CMS model, prefer its current title/url
                    // so renames/URL changes propagate without manual edits.
                    if ($c->subject_type && $c->subject_id && class_exists($c->subject_type)) {
                        $subject = ($c->subject_type)::find($c->subject_id);
                        if ($subject) {
                            if (method_exists($subject, 'getUrl')) {
                                try {
                                    $live = (string) $subject->getUrl();
                                    if ($live !== '') {
                                        $url = $live;
                                    }
                                } catch (\Throwable) {
                                    //
                                }
                            }

                            $name = $subject->name ?? $subject->title ?? null;
                            if (is_array($name)) {
                                $name = $name[app()->getLocale()] ?? reset($name) ?? null;
                            }
                            if (is_string($name) && $name !== '') {
                                $title = $name;
                            }

                            if ($type === '') {
                                $type = class_basename($c->subject_type);
                            }
                        }
                    }

                    return ['type' => $type, 'title' => $title, 'url' => $url];
                })
                ->filter(fn (array $c) => $c['url'] !== '' && $c['title'] !== '')
                ->values()
                ->all();
        }

        return $links->forLocale($draft->locale ?? 'nl', 20);
    }

    /**
     * Drop link-candidates whose URL already appears in another section's
     * body — ensures each internal link is used at most once across the
     * whole article. Retries on the same section still allow its own
     * previous URL so a regenerated section can keep (or swap) its link.
     *
     * @param  array<int, array{type: string, title: string, url: string}>  $candidates
     * @param  \Illuminate\Support\Collection<int, ContentDraftSection>     $allSections
     * @return array<int, array{type: string, title: string, url: string}>
     */
    private function filterAlreadyUsed(array $candidates, $allSections, int $currentSectionId): array
    {
        $used = [];
        foreach ($allSections as $s) {
            if ($s->id === $currentSectionId) {
                continue;
            }
            $body = (string) ($s->body ?? '');
            if ($body === '') {
                continue;
            }
            if (preg_match_all('/<a[^>]+href\s*=\s*(?:\"([^\"]*)\"|\'([^\']*)\')/i', $body, $m)) {
                foreach ($m[1] as $i => $url) {
                    $url = $url !== '' ? $url : ($m[2][$i] ?? '');
                    if ($url !== '') {
                        $used[$this->normalizeUrl($url)] = true;
                    }
                }
            }
        }

        if (empty($used)) {
            return $candidates;
        }

        return array_values(array_filter(
            $candidates,
            fn (array $c) => ! isset($used[$this->normalizeUrl((string) ($c['url'] ?? ''))]),
        ));
    }

    private function normalizeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        // Strip trailing slash and fragment so minor formatting differences
        // don't cause duplicate-link logic to miss a previously-used URL.
        $url = preg_replace('/#.*$/', '', $url) ?? $url;

        return rtrim($url, '/');
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

Interne link-kandidaten (beschikbaar voor DEZE sectie — al gebruikt in eerdere secties is eruit gefilterd):
{$candidatesJson}

Regels (hard):
- Retourneer HTML-string in veld "body".
- Alleen deze tags: p, ul, ol, li, strong, em, a.
- Geen img, geen script, geen style, geen heading tags (h1-h6).
- Maximaal 2 interne links als <a href='...'>anchor</a>, en alleen als ze thematisch écht passen. Gebruik alleen URLs die exact in bovenstaande lijst staan.
- Is de lijst leeg of past er niks, dan geen enkele interne link (liever geen dan een geforceerde).
- Gebruik elke URL maximaal één keer in deze sectie.
- Laat andere secties ongemoeid, schrijf alleen deze sectie.

Retourneer JSON: {"body": "<p>...</p>"}
TXT;
    }
}

<?php

namespace Dashed\DashedMarketing\Jobs;

use Dashed\DashedAi\Facades\Ai;
use Dashed\DashedMarketing\Facades\ContentTemplates;
use Dashed\DashedMarketing\Models\ContentCluster;
use Dashed\DashedMarketing\Models\ContentDraft;
use Dashed\DashedMarketing\Models\Keyword;
use Dashed\DashedMarketing\Services\ArticleSanitizer;
use Dashed\DashedMarketing\Services\ContentMatcher;
use Dashed\DashedMarketing\Services\EmbeddingService;
use Dashed\DashedMarketing\Services\SocialContextBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateContentDraftJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $overrideAction
     */
    public function __construct(
        public int $keywordId,
        public array $overrideAction = [],
    ) {}

    public function handle(
        ContentMatcher $matcher,
        ArticleSanitizer $sanitizer,
        EmbeddingService $embeddings,
    ): void {
        $keyword = Keyword::findOrFail($this->keywordId);
        $cluster = $keyword->contentClusters()->first();
        if ($cluster === null) {
            return;
        }

        $contentType = $this->overrideAction['content_type'] ?? $cluster->content_type;
        if (! ContentTemplates::has($contentType)) {
            Log::warning("GenerateContentDraftJob: no template for content type {$contentType}");

            return;
        }

        $template = ContentTemplates::make($contentType);

        $match = $this->overrideAction['match'] ?? $matcher->match($keyword);
        if ($match !== null) {
            $keyword->update([
                'matched_subject_type' => $match['subject_type'],
                'matched_subject_id' => $match['subject_id'],
                'match_score' => $match['score'],
                'match_strategy' => $match['strategy'],
            ]);
        }

        $prompt = $this->buildPrompt($keyword, $cluster, $template, $match);
        $content = Ai::json($prompt) ?? [];

        if (empty($content)) {
            Log::warning("GenerateContentDraftJob: AI returned empty content for keyword {$keyword->id}");

            return;
        }

        $content = $this->sanitizeContent($content, $sanitizer);

        // (legacy branch that created improvement rows was dropped in v4.12 — use SeoAudit flow for existing entities)
        ContentDraft::create([
            'content_cluster_id' => $cluster->id,
            'keyword' => $keyword->keyword,
            'locale' => $keyword->locale ?? 'nl',
            'status' => 'ready',
            'h2_sections' => $content['h2_sections'] ?? null,
            'article_content' => $content,
        ]);
    }

    protected function buildPrompt(Keyword $keyword, ContentCluster $cluster, $template, ?array $match): string
    {
        $brandContext = '';
        if (class_exists(SocialContextBuilder::class)) {
            try {
                $brandContext = app(SocialContextBuilder::class)->build('blog');
            } catch (\Throwable) {
                $brandContext = '';
            }
        }

        $templateContext = $template->promptContext();
        $related = $cluster->keywords()->pluck('keyword')->implode(', ');

        $entityContext = '';
        if ($match !== null) {
            $entity = ($match['subject_type'])::find($match['subject_id']);
            if ($entity !== null) {
                $title = $entity->name ?? $entity->title ?? '';
                $slug = $entity->slug ?? '';
                $meta = $entity->meta_description ?? '';
                $entityContext = "\n\nBestaande entity die verbeterd wordt:\nTitle: {$title}\nSlug: {$slug}\nHuidige meta_description: {$meta}";
            }
        }

        $hygieneRules = <<<'RULES'

Prompt-hygiëne (hard):
- Geen em-dashes (–, -). Gebruik komma's.
- Vermijd AI-clichés ("in dit artikel gaan we", "duik in", "ontdek de geheimen van", "welnu").
- Actieve vorm, korte zinnen, "je"-vorm.
- Geen loze superlatieven.
- Verzin geen feiten.
RULES;

        return "{$brandContext}\n\n{$templateContext}\n\nKeyword: {$keyword->keyword}\nCluster: {$cluster->name}\nVerwante keywords: {$related}{$entityContext}\n\n{$hygieneRules}\n\nRetourneer ONLY valid JSON conform het template schema.";
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    protected function sanitizeContent(array $content, ArticleSanitizer $sanitizer): array
    {
        if (isset($content['h2_sections']) && is_array($content['h2_sections'])) {
            $content['h2_sections'] = $sanitizer->sanitizeSections($content['h2_sections']);
        }

        if (isset($content['blocks']) && is_array($content['blocks'])) {
            foreach ($content['blocks'] as &$block) {
                if (isset($block['data']) && is_array($block['data'])) {
                    foreach ($block['data'] as $key => $value) {
                        if (is_string($value)) {
                            $block['data'][$key] = $sanitizer->sanitize($value);
                        }
                    }
                }
            }
            unset($block);
        }

        return $content;
    }
}

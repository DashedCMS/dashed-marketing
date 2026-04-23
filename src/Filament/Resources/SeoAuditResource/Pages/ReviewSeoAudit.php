<?php

namespace Dashed\DashedMarketing\Filament\Resources\SeoAuditResource\Pages;

use Dashed\DashedMarketing\Filament\Resources\SeoAuditResource;
use Dashed\DashedMarketing\Jobs\GenerateSeoAuditJob;
use Dashed\DashedMarketing\Models\ContentApplyLog;
use Dashed\DashedMarketing\Models\SeoAudit;
use Dashed\DashedMarketing\Services\SeoAuditApplier;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;

class ReviewSeoAudit extends Page
{
    protected static string $resource = SeoAuditResource::class;

    protected string $view = 'dashed-marketing::filament.pages.review-seo-audit';

    public SeoAudit $record;

    /**
     * @var array<string, array<int>>
     */
    public array $selected = [
        'meta' => [],
        'blocks' => [],
        'faqs' => [],
        'structured_data' => [],
    ];

    /**
     * @var array<int, string>
     */
    public array $editedMeta = [];

    /**
     * @var array<int, string>
     */
    public array $editedBlocks = [];

    /**
     * @var array<int, array{question: string, answer: string}>
     */
    public array $editedFaqs = [];

    public string $faqApplyTarget = 'existing';

    public ?string $outlineH1 = null;

    public ?string $outlineSummary = null;

    /**
     * @var array<int, array{level: int, text: string}>
     */
    public array $outlineHeadings = [];

    public bool $outlineGenerating = false;

    public string $subjectUpdatedAtSnapshot = '';

    public string $newLinkAnchor = '';

    public string $newLinkUrl = '';

    public string $newLinkContext = '';

    public string $newLinkPriority = 'medium';

    public function mount(SeoAudit $record): void
    {
        $this->record = $record;
        $this->subjectUpdatedAtSnapshot = $record->subject?->updated_at?->toIso8601String() ?? '';
        $this->faqApplyTarget = $this->detectExistingFaqBlock($record) ? 'existing' : 'new';

        $this->seedEditedValues();
        $this->defaultSelectAll();
    }

    protected function detectExistingFaqBlock(SeoAudit $record): bool
    {
        $subject = $record->subject;
        if (! $subject || ! method_exists($subject, 'customBlocks') || ! $subject->customBlocks) {
            return false;
        }

        try {
            $blocks = (array) ($subject->customBlocks->getTranslation('blocks', $record->locale) ?? []);
        } catch (\Throwable) {
            return false;
        }

        $faqTypes = (array) config('dashed-marketing.seo_faq_block_types', ['faq']);
        foreach ($blocks as $b) {
            if (in_array($b['type'] ?? null, $faqTypes, true)) {
                return true;
            }
        }

        return false;
    }

    protected function seedEditedValues(): void
    {
        $this->editedMeta = [];
        foreach ($this->record->metaSuggestions as $s) {
            $this->editedMeta[$s->id] = (string) $s->suggested_value;
        }

        $this->editedBlocks = [];
        foreach ($this->record->blockSuggestions as $s) {
            $this->editedBlocks[$s->id] = (string) $s->suggested_value;
        }

        $this->editedFaqs = [];
        foreach ($this->record->faqSuggestions as $s) {
            $this->editedFaqs[$s->id] = [
                'question' => (string) $s->question,
                'answer' => (string) $s->answer,
            ];
        }

        $outline = $this->record->outline;
        $this->outlineH1 = $outline?->h1;
        $this->outlineSummary = $outline?->summary;
        $this->outlineHeadings = collect($outline?->headings ?? [])
            ->map(fn ($h) => [
                'level' => in_array((int) ($h['level'] ?? 2), [2, 3], true) ? (int) $h['level'] : 2,
                'text' => (string) ($h['text'] ?? ''),
            ])
            ->filter(fn ($h) => $h['text'] !== '')
            ->values()
            ->all();
    }

    public function pollAudit(): void
    {
        $freshStatus = DB::table('dashed__seo_audits')
            ->where('id', $this->record->id)
            ->value('status');

        if ($freshStatus !== null && $freshStatus !== $this->record->status) {
            $this->record->refresh();
            $this->seedEditedValues();
            $this->defaultSelectAll();
        }
    }

    protected function defaultSelectAll(): void
    {
        $this->selected = [
            'meta' => $this->record->metaSuggestions()->whereIn('status', ['pending', 'edited'])->pluck('id')->all(),
            'blocks' => $this->record->blockSuggestions()->whereIn('status', ['pending', 'edited'])->pluck('id')->all(),
            'faqs' => $this->record->faqSuggestions()->whereIn('status', ['pending', 'edited'])->pluck('id')->all(),
            'structured_data' => $this->record->structuredDataSuggestions()->whereIn('status', ['pending', 'edited'])->pluck('id')->all(),
        ];
    }

    public function selectAll(): void
    {
        $this->defaultSelectAll();
    }

    public function deselectAll(): void
    {
        $this->selected = ['meta' => [], 'blocks' => [], 'faqs' => [], 'structured_data' => []];
    }

    public function applySelected(): void
    {
        $this->syncEditedValuesToDb();

        try {
            $res = app(SeoAuditApplier::class)->applySelected(
                $this->record,
                array_merge($this->selected, ['faq_target' => $this->faqApplyTarget]),
                userId: auth()->id(),
            );
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Toepassen mislukt')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title($res->summary())
            ->success()
            ->send();

        $this->record->refresh();
        $this->seedEditedValues();
        $this->defaultSelectAll();
    }

    public function applyAllPendingInTab(string $tab): void
    {
        $map = [
            'meta' => $this->record->metaSuggestions()->whereIn('status', ['pending', 'edited'])->pluck('id')->all(),
            'blocks' => $this->record->blockSuggestions()->whereIn('status', ['pending', 'edited'])->pluck('id')->all(),
            'faqs' => $this->record->faqSuggestions()->whereIn('status', ['pending', 'edited'])->pluck('id')->all(),
            'structured_data' => $this->record->structuredDataSuggestions()->whereIn('status', ['pending', 'edited'])->pluck('id')->all(),
        ];
        $this->selected = array_merge($this->selected, [$tab => $map[$tab] ?? []]);
        $this->applySelected();
    }

    public function applyMetaOne(int $id): void
    {
        $this->selected['meta'] = [$id];
        $this->applySelected();
    }

    public function rejectMetaOne(int $id): void
    {
        $this->record->metaSuggestions()->where('id', $id)->update(['status' => 'rejected']);
        Notification::make()->title('Meta voorstel afgewezen')->send();
    }

    public function applyBlockOne(int $id): void
    {
        $this->selected['blocks'] = [$id];
        $this->applySelected();
    }

    public function rejectBlockOne(int $id): void
    {
        $this->record->blockSuggestions()->where('id', $id)->update(['status' => 'rejected']);
        Notification::make()->title('Blok voorstel afgewezen')->send();
    }

    public function acknowledgeLink(int $id): void
    {
        $this->record->internalLinkSuggestions()->where('id', $id)->update(['status' => 'acknowledged']);
        Notification::make()->title('Link gemarkeerd als bekeken')->send();
    }

    public function rejectLink(int $id): void
    {
        $this->record->internalLinkSuggestions()->where('id', $id)->update(['status' => 'rejected']);
        Notification::make()->title('Link afgewezen')->send();
    }

    public function saveOutline(): void
    {
        $headings = array_values(array_filter(array_map(fn ($h) => [
            'level' => in_array((int) ($h['level'] ?? 2), [2, 3], true) ? (int) $h['level'] : 2,
            'text' => trim((string) ($h['text'] ?? '')),
        ], $this->outlineHeadings), fn ($h) => $h['text'] !== ''));

        $this->record->outline()->updateOrCreate(
            ['audit_id' => $this->record->id],
            [
                'h1' => $this->outlineH1 !== null && trim($this->outlineH1) !== '' ? trim($this->outlineH1) : null,
                'summary' => $this->outlineSummary !== null && trim($this->outlineSummary) !== '' ? trim($this->outlineSummary) : null,
                'headings' => $headings,
            ]
        );

        Notification::make()->title('Outline opgeslagen')->success()->send();
    }

    public function addOutlineHeading(): void
    {
        $this->outlineHeadings[] = ['level' => 2, 'text' => ''];
    }

    public function removeOutlineHeading(int $index): void
    {
        if (isset($this->outlineHeadings[$index])) {
            unset($this->outlineHeadings[$index]);
            $this->outlineHeadings = array_values($this->outlineHeadings);
        }
    }

    public function generateOutlineContent(): void
    {
        $this->saveOutline();
        $this->record->refresh();
        $outline = $this->record->outline;
        if (! $outline || empty($outline->headings)) {
            Notification::make()
                ->title('Geen headings om content voor te genereren')
                ->warning()
                ->send();

            return;
        }

        $this->outlineGenerating = true;

        try {
            $ctx = $this->buildOutlineContext();
            $created = 0;

            // Wipe previous is_new_block=true suggestions for this audit so re-generation
            // replaces rather than accumulates. Keep non-new block suggestions untouched.
            $this->record->blockSuggestions()->where('is_new_block', true)->delete();

            $sort = 0;
            foreach ($outline->headings as $heading) {
                $level = (int) ($heading['level'] ?? 2);
                $text = trim((string) ($heading['text'] ?? ''));
                if ($text === '') {
                    continue;
                }

                $response = [];
                try {
                    $response = \Dashed\DashedAi\Facades\Ai::json(
                        \Dashed\DashedMarketing\Services\Prompts\SeoAuditPromptBuilder::outlineContent(
                            $ctx,
                            $text,
                            $level,
                            (string) ($outline->h1 ?? ''),
                            (string) ($outline->summary ?? ''),
                        )
                    ) ?? [];
                } catch (\Throwable $e) {
                    $response = [];
                }

                $body = '';
                foreach (['body', 'html', 'content', 'text'] as $key) {
                    if (! empty($response[$key]) && is_string($response[$key])) {
                        $body = trim($response[$key]);

                        break;
                    }
                }

                $tag = 'h'.$level;
                $html = "<{$tag}>".e($text)."</{$tag}>".$body;

                $this->record->blockSuggestions()->create([
                    'block_index' => null,
                    'block_key' => 'outline.'.$sort,
                    'block_type' => 'content',
                    'field_key' => '_new',
                    'is_new_block' => true,
                    'suggested_value' => $html,
                    'reason' => 'Op basis van outline heading: '.$text,
                    'priority' => 'medium',
                    'status' => 'pending',
                ]);

                $sort++;
                $created++;
            }

            $outline->update(['content_generated_at' => now()]);

            Notification::make()
                ->title("{$created} content-blok voorstellen gegenereerd")
                ->body('Vink ze aan in de Blokken-tab en klik "Toepassen" om ze als blokken toe te voegen aan de pagina.')
                ->success()
                ->send();
        } finally {
            $this->outlineGenerating = false;
            $this->record->refresh();
            $this->seedEditedValues();
        }
    }

    protected function buildOutlineContext(): array
    {
        $subject = $this->record->subject;
        $locale = $this->record->locale;

        $name = '';
        if ($subject) {
            $raw = $subject->name ?? $subject->title ?? null;
            if (is_array($raw)) {
                $name = (string) ($raw[$locale] ?? reset($raw) ?? '');
            } else {
                $name = (string) $raw;
            }
        }

        $url = '';
        if ($subject && method_exists($subject, 'getUrl')) {
            try {
                $url = (string) $subject->getUrl();
            } catch (\Throwable) {
                //
            }
        }

        $brand = '';
        try {
            $brand = app(\Dashed\DashedMarketing\Services\SocialContextBuilder::class)->build('seo');
        } catch (\Throwable) {
            //
        }

        $seededKeywords = [];
        try {
            $seededKeywords = \Dashed\DashedMarketing\Models\Keyword::query()
                ->where('locale', $locale)
                ->where(function ($q) {
                    $q->whereNull('status')->orWhere('status', '!=', 'rejected');
                })
                ->pluck('keyword')
                ->map(fn ($k) => (string) $k)
                ->all();
        } catch (\Throwable) {
            //
        }

        return [
            'subject' => [
                'type' => class_basename($this->record->subject_type),
                'id' => $this->record->subject_id,
                'name' => $name,
                'url' => $url,
            ],
            'locale' => $locale,
            'brand' => $brand,
            'user_instruction' => $this->record->instruction,
            'seeded_keywords' => $seededKeywords,
        ];
    }

    public function addInternalLink(): void
    {
        $anchor = trim($this->newLinkAnchor);
        $url = trim($this->newLinkUrl);
        $context = trim($this->newLinkContext);

        if ($anchor === '' || $url === '' || $context === '') {
            Notification::make()
                ->title('Vul anker, URL en context in')
                ->warning()
                ->send();

            return;
        }

        $priority = in_array($this->newLinkPriority, ['high', 'medium', 'low'], true)
            ? $this->newLinkPriority
            : 'medium';

        $this->record->internalLinkSuggestions()->create([
            'anchor_text' => mb_substr($anchor, 0, 500),
            'target_url' => mb_substr($url, 0, 2048),
            'context_description' => $context,
            'priority' => $priority,
            'status' => 'pending',
        ]);

        $this->newLinkAnchor = '';
        $this->newLinkUrl = '';
        $this->newLinkContext = '';
        $this->newLinkPriority = 'medium';

        $this->record->refresh();

        Notification::make()
            ->title('Interne link toegevoegd')
            ->success()
            ->send();
    }

    public function rollbackAudit(): void
    {
        try {
            app(SeoAuditApplier::class)->rollbackAudit($this->record);
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Rollback mislukt')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()->title('Audit teruggedraaid')->success()->send();
        $this->record->refresh();
    }

    public function revertLog(int $logId): void
    {
        $log = ContentApplyLog::find($logId);
        if (! $log) {
            Notification::make()->title('Log niet gevonden')->danger()->send();

            return;
        }

        try {
            app(SeoAuditApplier::class)->revertOne($log);
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Revert mislukt')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()->title('Wijziging teruggedraaid')->success()->send();
    }

    public function regenerate(): void
    {
        GenerateSeoAuditJob::dispatch(
            $this->record->subject_type,
            $this->record->subject_id,
            auth()->id(),
            $this->record->instruction,
        );
        Notification::make()
            ->title('Nieuwe audit gestart')
            ->body('De bestaande audit wordt gearchiveerd. Ververs over een moment voor de nieuwe resultaten.')
            ->send();
    }

    protected function syncEditedValuesToDb(): void
    {
        foreach ($this->editedMeta as $id => $value) {
            $sug = $this->record->metaSuggestions()->find($id);
            if ($sug && $sug->suggested_value !== $value) {
                $sug->update(['suggested_value' => $value, 'status' => 'edited']);
            }
        }
        foreach ($this->editedBlocks as $id => $value) {
            $sug = $this->record->blockSuggestions()->find($id);
            if ($sug && $sug->suggested_value !== $value) {
                $sug->update(['suggested_value' => $value, 'status' => 'edited']);
            }
        }
        foreach ($this->editedFaqs as $id => $pair) {
            $sug = $this->record->faqSuggestions()->find($id);
            if (! $sug) {
                continue;
            }
            $dirty = false;
            if ($sug->question !== ($pair['question'] ?? '')) {
                $dirty = true;
            }
            if ($sug->answer !== ($pair['answer'] ?? '')) {
                $dirty = true;
            }
            if ($dirty) {
                $sug->update([
                    'question' => $pair['question'] ?? $sug->question,
                    'answer' => $pair['answer'] ?? $sug->answer,
                    'status' => 'edited',
                ]);
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAppliedLogs(): array
    {
        return ContentApplyLog::where('audit_id', $this->record->id)
            ->whereNull('reverted_at')
            ->orderByDesc('applied_at')
            ->get()
            ->toArray();
    }
}

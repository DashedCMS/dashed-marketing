<?php

namespace Dashed\DashedMarketing\Filament\Resources\SeoAuditResource\Pages;

use Dashed\DashedMarketing\Filament\Resources\SeoAuditResource;
use Dashed\DashedMarketing\Jobs\GenerateOutlineContentJob;
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
        $this->outlineGenerating = (bool) $record->outline?->content_generating_at;
    }

    protected function detectExistingFaqBlock(SeoAudit $record): bool
    {
        $subject = $record->subject;
        if (! $subject) {
            return false;
        }

        $blocks = [];
        $translatable = (array) ($subject->translatable ?? []);
        if (in_array('content', $translatable, true) && method_exists($subject, 'getTranslation')) {
            try {
                $content = $subject->getTranslation('content', $record->locale);
                if (is_array($content)) {
                    $blocks = $content;
                }
            } catch (\Throwable) {
                //
            }
        }

        if (empty($blocks) && method_exists($subject, 'customBlocks')) {
            $customBlocks = $subject->customBlocks()->first();
            if ($customBlocks) {
                try {
                    $blocks = (array) ($customBlocks->getTranslation('blocks', $record->locale) ?? []);
                } catch (\Throwable) {
                    //
                }
            }
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

        $generatingAt = DB::table('dashed__seo_audit_outlines')
            ->where('audit_id', $this->record->id)
            ->value('content_generating_at');

        $isGenerating = $generatingAt !== null;

        if ($isGenerating !== $this->outlineGenerating) {
            $this->outlineGenerating = $isGenerating;

            if (! $isGenerating) {
                $this->record->refresh();
                $this->seedEditedValues();
                $this->defaultSelectAll();
            }
        }

        $this->refreshFaqApplyTarget();
    }

    protected function refreshFaqApplyTarget(): void
    {
        $subject = $this->record->subject;
        if ($subject && method_exists($subject, 'customBlocks')) {
            $subject->unsetRelation('customBlocks');
        }

        $this->faqApplyTarget = $this->detectExistingFaqBlock($this->record) ? 'existing' : 'new';
    }

    protected function defaultSelectAll(): void
    {
        $allowed = ['pending', 'edited', 'applied'];

        $this->selected = [
            'meta' => $this->record->metaSuggestions()->whereIn('status', $allowed)->pluck('id')->all(),
            'blocks' => $this->record->blockSuggestions()->whereIn('status', $allowed)->pluck('id')->all(),
            'faqs' => $this->record->faqSuggestions()->whereIn('status', $allowed)->pluck('id')->all(),
            'structured_data' => $this->record->structuredDataSuggestions()->whereIn('status', $allowed)->pluck('id')->all(),
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
        $this->refreshFaqApplyTarget();
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

        if ($outline->content_generating_at !== null) {
            Notification::make()
                ->title('Content-generatie loopt al')
                ->body('Even geduld - de voorstellen verschijnen zodra de job klaar is.')
                ->warning()
                ->send();

            return;
        }

        $outline->update(['content_generating_at' => now()]);
        GenerateOutlineContentJob::dispatch($this->record->id);

        $this->outlineGenerating = true;

        Notification::make()
            ->title('Content-generatie gestart')
            ->body('De voorstellen verschijnen automatisch in de Blokken-tab zodra ze klaar zijn.')
            ->success()
            ->send();
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

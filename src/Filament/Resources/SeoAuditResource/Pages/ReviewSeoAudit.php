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

    public string $subjectUpdatedAtSnapshot = '';

    public function mount(SeoAudit $record): void
    {
        $this->record = $record;
        $this->subjectUpdatedAtSnapshot = $record->subject?->updated_at?->toIso8601String() ?? '';

        $this->seedEditedValues();
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
    }

    public function pollAudit(): void
    {
        $freshStatus = DB::table('dashed__seo_audits')
            ->where('id', $this->record->id)
            ->value('status');

        if ($freshStatus !== null && $freshStatus !== $this->record->status) {
            $this->record->refresh();
            $this->seedEditedValues();
        }
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
        $this->selected = ['meta' => [], 'blocks' => [], 'faqs' => [], 'structured_data' => []];
        $this->seedEditedValues();
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

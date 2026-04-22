<?php

namespace Dashed\DashedMarketing\Filament\Resources\SeoImprovementResource\Pages;

use Dashed\DashedMarketing\Filament\Resources\SeoImprovementResource;
use Dashed\DashedMarketing\Jobs\ApplyContentImprovementJob;
use Dashed\DashedMarketing\Models\ContentApplyLog;
use Dashed\DashedMarketing\Models\SeoImprovement;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;

class ReviewSeoImprovement extends Page
{
    protected static string $resource = SeoImprovementResource::class;

    protected string $view = 'dashed-marketing::filament.pages.review-seo-improvement';

    public SeoImprovement $record;

    public array $editedValues = [];

    public string $subjectUpdatedAtSnapshot = '';

    public function mount(SeoImprovement $record): void
    {
        $this->record = $record;
        $this->subjectUpdatedAtSnapshot = $record->subject?->updated_at?->toIso8601String() ?? '';

        // Seed editedValues zodat de wire:model textarea's de voorstellen tonen.
        // Zonder dit blijven de velden leeg omdat Livewire de state aanhoudt
        // en de default content tussen de textarea-tags negeert.
        $seeded = [];
        foreach ((array) $record->field_proposals as $key => $value) {
            $seeded[$key] = is_array($value)
                ? json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                : (string) $value;
        }
        $this->editedValues = $seeded;
    }

    public function applyProposal(string $key): void
    {
        if (! $this->checkConcurrency()) {
            return;
        }

        ApplyContentImprovementJob::dispatchSync(
            $this->record->id,
            $key,
            $this->editedValues[$key] ?? null,
            auth()->id(),
        );

        $this->record->refresh();
        Notification::make()->title("Toegepast: {$key}")->success()->send();
    }

    public function rejectProposal(string $key): void
    {
        $this->record->markProposal($key, 'rejected');
        Notification::make()->title("Afgewezen: {$key}")->warning()->send();
    }

    public function applyAll(): void
    {
        if (! $this->checkConcurrency()) {
            return;
        }

        foreach (array_keys($this->record->block_proposals ?? []) as $key) {
            $proposalKey = "block.{$key}";
            if ($this->record->proposalStatus($proposalKey) !== 'pending') {
                continue;
            }
            ApplyContentImprovementJob::dispatchSync($this->record->id, $proposalKey, null, auth()->id());
        }

        foreach (array_keys($this->record->field_proposals ?? []) as $key) {
            if ($this->record->proposalStatus($key) !== 'pending') {
                continue;
            }
            ApplyContentImprovementJob::dispatchSync($this->record->id, $key, null, auth()->id());
        }

        $this->record->refresh();
        Notification::make()->title('Alle pending voorstellen toegepast')->success()->send();
    }

    public function rejectAll(): void
    {
        foreach (array_keys($this->record->block_proposals ?? []) as $key) {
            $this->record->markProposal("block.{$key}", 'rejected');
        }
        foreach (array_keys($this->record->field_proposals ?? []) as $key) {
            $this->record->markProposal($key, 'rejected');
        }
        Notification::make()->title('Alle voorstellen afgewezen')->warning()->send();
    }

    public function revertProposal(int $logId): void
    {
        $log = ContentApplyLog::findOrFail($logId);
        $subject = $log->subject;
        if ($subject === null) {
            return;
        }

        $previous = json_decode($log->previous_value ?? 'null', true);
        if (str_starts_with($log->field_key, 'block.')) {
            $blockKey = substr($log->field_key, 6);
            $blocks = $subject->customBlocks?->blocks ?? [];
            $blocks[$blockKey] = $previous;
            if (method_exists($subject, 'customBlocks')) {
                $subject->customBlocks()->update(['blocks' => $blocks]);
            }
        } else {
            $subject->update([$log->field_key => $previous]);
        }

        $log->update(['reverted_at' => now()]);
        $this->record->markProposal($log->field_key, 'pending');
        Notification::make()->title("Teruggedraaid: {$log->field_key}")->success()->send();
    }

    protected function checkConcurrency(): bool
    {
        $current = $this->record->subject?->updated_at?->toIso8601String();
        if ($current !== $this->subjectUpdatedAtSnapshot) {
            Notification::make()
                ->title('Entity is gewijzigd sinds dit voorstel werd gemaakt')
                ->body('Bekijk het verschil opnieuw voor je toepast.')
                ->warning()
                ->send();

            return false;
        }

        return true;
    }

    public function getAppliedLogs(): array
    {
        return ContentApplyLog::query()
            ->where('seo_improvement_id', $this->record->id)
            ->whereNull('reverted_at')
            ->get()
            ->toArray();
    }
}

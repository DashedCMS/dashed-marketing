<x-filament-panels::page>
    @php
        $subject = $record->subject;
        $subjectName = null;
        if ($subject) {
            $subjectName = $subject->name ?? $subject->title ?? null;
            if (is_array($subjectName)) {
                $subjectName = $subjectName[app()->getLocale()] ?? reset($subjectName) ?? null;
            }
        }
        $subjectName = $subjectName ?: 'Record #'.$record->subject_id;
        $fieldProposals = (array) ($record->field_proposals ?? []);
        $blockProposals = (array) ($record->block_proposals ?? []);
    @endphp

    <div class="space-y-6">
        {{-- Header card --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex flex-col gap-4 p-6 md:flex-row md:items-start md:justify-between">
                <div class="space-y-1">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        {{ class_basename($record->subject_type) }} @if($record->subject_id) · ID {{ $record->subject_id }}@endif
                    </p>
                    <h2 class="text-xl font-semibold text-gray-950 dark:text-white">
                        {{ $subjectName }}
                    </h2>
                    @if($record->analysis_summary)
                        <p class="max-w-2xl text-sm text-gray-600 dark:text-gray-300">{{ $record->analysis_summary }}</p>
                    @endif
                </div>
                <div class="flex flex-wrap gap-2">
                    <x-filament::button wire:click="applyAll" icon="heroicon-o-check-circle">
                        Apply all pending
                    </x-filament::button>
                    <x-filament::button color="danger" wire:click="rejectAll" icon="heroicon-o-x-circle">
                        Reject all pending
                    </x-filament::button>
                </div>
            </div>
        </div>

        {{-- Field proposals --}}
        @if(! empty($fieldProposals))
            <section class="space-y-3">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">Veld-voorstellen</h3>

                @foreach($fieldProposals as $key => $newValue)
                    @php
                        $status = $record->proposalStatus($key);
                        $currentValue = $record->subject?->{$key} ?? null;

                        $statusColor = match ($status) {
                            'applied' => 'success',
                            'rejected' => 'danger',
                            'edited' => 'info',
                            default => 'warning',
                        };
                    @endphp

                    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                        <div class="flex items-start justify-between gap-4 p-5">
                            <div class="space-y-1">
                                <code class="rounded bg-gray-100 px-2 py-0.5 text-sm font-medium text-gray-800 dark:bg-white/10 dark:text-gray-100">{{ $key }}</code>
                            </div>
                            <x-filament::badge :color="$statusColor">{{ strtoupper($status) }}</x-filament::badge>
                        </div>

                        <div class="grid grid-cols-1 gap-4 border-t border-gray-200 p-5 dark:border-white/10 md:grid-cols-2">
                            <div class="space-y-2">
                                <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Huidig</div>
                                <div class="max-h-60 overflow-auto whitespace-pre-wrap break-words rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm text-gray-800 dark:border-white/10 dark:bg-gray-950 dark:text-gray-200">{{ is_array($currentValue) ? json_encode($currentValue, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : (string) $currentValue }}</div>
                            </div>
                            <div class="space-y-2">
                                <div class="text-xs font-medium uppercase tracking-wide text-primary-600 dark:text-primary-400">Voorgesteld (bewerkbaar)</div>
                                <textarea
                                    wire:model="editedValues.{{ $key }}"
                                    rows="6"
                                    class="block w-full resize-y rounded-lg border-gray-300 bg-white text-sm text-gray-950 shadow-sm transition focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-950 dark:text-white dark:focus:border-primary-500"
                                >{{ is_array($newValue) ? json_encode($newValue, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : (string) $newValue }}</textarea>
                            </div>
                        </div>

                        @if(in_array($status, ['pending', 'edited']))
                            <div class="flex flex-wrap gap-2 border-t border-gray-200 p-4 dark:border-white/10">
                                <x-filament::button size="sm" wire:click="applyProposal('{{ $key }}')" icon="heroicon-o-check">
                                    Apply
                                </x-filament::button>
                                <x-filament::button size="sm" color="danger" wire:click="rejectProposal('{{ $key }}')" icon="heroicon-o-x-mark">
                                    Reject
                                </x-filament::button>
                            </div>
                        @endif
                    </div>
                @endforeach
            </section>
        @endif

        {{-- Block proposals --}}
        @if(! empty($blockProposals))
            <section class="space-y-3">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">Blok-voorstellen</h3>

                @foreach($blockProposals as $key => $newBlock)
                    @php
                        $proposalKey = "block.{$key}";
                        $status = $record->proposalStatus($proposalKey);
                        $currentBlocks = $record->subject?->customBlocks?->blocks ?? [];
                        $currentBlock = $currentBlocks[$key] ?? null;

                        $statusColor = match ($status) {
                            'applied' => 'success',
                            'rejected' => 'danger',
                            'edited' => 'info',
                            default => 'warning',
                        };
                    @endphp

                    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                        <div class="flex items-start justify-between gap-4 p-5">
                            <code class="rounded bg-gray-100 px-2 py-0.5 text-sm font-medium text-gray-800 dark:bg-white/10 dark:text-gray-100">Blok {{ $key }}</code>
                            <x-filament::badge :color="$statusColor">{{ strtoupper($status) }}</x-filament::badge>
                        </div>

                        <div class="grid grid-cols-1 gap-4 border-t border-gray-200 p-5 dark:border-white/10 md:grid-cols-2">
                            <div class="space-y-2">
                                <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Huidig</div>
                                <pre class="max-h-80 overflow-auto rounded-lg border border-gray-200 bg-gray-50 p-3 text-xs leading-relaxed text-gray-800 dark:border-white/10 dark:bg-gray-950 dark:text-gray-200">{{ json_encode($currentBlock, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                            </div>
                            <div class="space-y-2">
                                <div class="text-xs font-medium uppercase tracking-wide text-primary-600 dark:text-primary-400">Voorgesteld</div>
                                <pre class="max-h-80 overflow-auto rounded-lg border border-success-200 bg-success-50 p-3 text-xs leading-relaxed text-success-900 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-100">{{ json_encode($newBlock, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                            </div>
                        </div>

                        @if(in_array($status, ['pending', 'edited']))
                            <div class="flex flex-wrap gap-2 border-t border-gray-200 p-4 dark:border-white/10">
                                <x-filament::button size="sm" wire:click="applyProposal('{{ $proposalKey }}')" icon="heroicon-o-check">
                                    Apply
                                </x-filament::button>
                                <x-filament::button size="sm" color="danger" wire:click="rejectProposal('{{ $proposalKey }}')" icon="heroicon-o-x-mark">
                                    Reject
                                </x-filament::button>
                            </div>
                        @endif
                    </div>
                @endforeach
            </section>
        @endif

        {{-- Applied log --}}
        @if(count($this->getAppliedLogs()))
            <section class="space-y-3">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">Toegepaste wijzigingen</h3>

                <div class="fi-section overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr class="text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <th class="px-4 py-3">Veld/blok</th>
                                <th class="px-4 py-3">Wanneer</th>
                                <th class="px-4 py-3">Door</th>
                                <th class="px-4 py-3 text-right">Actie</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach($this->getAppliedLogs() as $log)
                                <tr class="text-gray-800 dark:text-gray-200">
                                    <td class="px-4 py-3 font-mono text-xs">{{ $log['field_key'] }}</td>
                                    <td class="px-4 py-3">{{ $log['applied_at'] }}</td>
                                    <td class="px-4 py-3">{{ $log['applied_by'] }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <x-filament::button size="sm" color="gray" wire:click="revertProposal({{ $log['id'] }})" icon="heroicon-o-arrow-uturn-left">
                                            Revert
                                        </x-filament::button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @if(empty($fieldProposals) && empty($blockProposals) && ! count($this->getAppliedLogs()))
            <div class="fi-section rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-500 dark:border-white/10 dark:bg-gray-900 dark:text-gray-400">
                Er zijn nog geen voorstellen om te reviewen.
            </div>
        @endif
    </div>
</x-filament-panels::page>

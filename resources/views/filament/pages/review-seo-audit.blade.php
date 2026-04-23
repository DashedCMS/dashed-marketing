<x-filament-panels::page>
    @php
        $record = $this->record;
        $subject = $record->subject;
        $subjectName = null;
        if ($subject) {
            $subjectName = $subject->name ?? $subject->title ?? null;
            if (is_array($subjectName)) {
                $subjectName = $subjectName[app()->getLocale()] ?? reset($subjectName) ?? null;
            }
        }
        $subjectName = $subjectName ?: 'Record #'.$record->subject_id;
        $breakdown = $record->score_breakdown ?? [];
    @endphp

    <div x-data="{ tab: 'overview' }" class="space-y-6">
        {{-- Header --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            @if($record->status === 'analyzing')
                <div wire:poll.5s="pollAudit" class="rounded-t-xl bg-info-50 p-4 text-info-700 dark:bg-info-900/20 dark:text-info-300">
                    <strong>Bezig met analyseren...</strong> {{ $record->progress_message ?? 'Moment...' }}
                </div>
            @endif

            <div class="flex flex-col gap-4 p-6 md:flex-row md:items-start md:justify-between">
                <div class="space-y-2">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        {{ class_basename($record->subject_type) }} @if($record->subject_id) · ID {{ $record->subject_id }}@endif
                    </p>
                    <h2 class="text-xl font-semibold text-gray-950 dark:text-white">{{ $subjectName }}</h2>
                    @if($record->analysis_summary)
                        <p class="max-w-2xl text-sm text-gray-600 dark:text-gray-300">{{ $record->analysis_summary }}</p>
                    @endif

                    @if($record->overall_score !== null)
                        <div class="flex items-center gap-2 pt-2">
                            <span class="text-2xl font-bold text-gray-950 dark:text-white">{{ $record->overall_score }}</span>
                            <span class="text-sm text-gray-500">overall score</span>
                        </div>
                        @if(!empty($breakdown))
                            <div class="grid grid-cols-2 gap-2 pt-1 md:grid-cols-6">
                                @foreach($breakdown as $k => $v)
                                    <div class="text-xs">
                                        <div class="text-gray-500 dark:text-gray-400">{{ $k }}</div>
                                        <div class="font-medium">{{ $v ?? '-' }}</div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    @endif
                </div>
                @php
                    $subjectEditUrl = null;
                    $subjectFrontendUrl = null;
                    if ($subject) {
                        try {
                            $panel = \Filament\Facades\Filament::getCurrentOrDefaultPanel();
                            $resourceClass = $panel?->getModelResource($record->subject_type);
                            if ($resourceClass && method_exists($resourceClass, 'getUrl')) {
                                $subjectEditUrl = $resourceClass::getUrl('edit', ['record' => $subject->getKey()]);
                            }
                        } catch (\Throwable) {
                            //
                        }
                        if (method_exists($subject, 'getUrl')) {
                            try {
                                $subjectFrontendUrl = (string) $subject->getUrl();
                            } catch (\Throwable) {
                                $subjectFrontendUrl = null;
                            }
                        }
                    }
                @endphp

                <div class="flex flex-wrap gap-2">
                    @if($subjectEditUrl)
                        <x-filament::button tag="a" href="{{ $subjectEditUrl }}" target="_blank" color="gray" icon="heroicon-o-pencil-square">
                            Bewerk record
                        </x-filament::button>
                    @endif
                    @if($subjectFrontendUrl)
                        <x-filament::button tag="a" href="{{ $subjectFrontendUrl }}" target="_blank" color="gray" icon="heroicon-o-eye">
                            Bekijk op site
                        </x-filament::button>
                    @endif
                    <x-filament::button wire:click="applySelected" icon="heroicon-o-check-circle">
                        Geselecteerde toepassen
                    </x-filament::button>
                    @if(in_array($record->status, ['partially_applied','fully_applied']))
                        <x-filament::button color="danger" wire:click="rollbackAudit" icon="heroicon-o-arrow-uturn-left">
                            Rol alles terug
                        </x-filament::button>
                    @endif
                    <x-filament::button color="gray" wire:click="regenerate" icon="heroicon-o-arrow-path">
                        Opnieuw genereren
                    </x-filament::button>
                </div>
            </div>
        </div>

        {{-- Tabs --}}
        <div class="flex flex-wrap gap-2 border-b border-gray-200 dark:border-white/10">
            @foreach(['overview'=>'Overzicht','keywords'=>'Keywords','meta'=>'Meta','blocks'=>'Blokken','faqs'=>'FAQ\'s','structured_data'=>'Structured data','internal_links'=>'Interne links'] as $key => $label)
                <button
                    type="button"
                    x-on:click="tab = '{{ $key }}'"
                    :class="tab === '{{ $key }}' ? 'border-primary-600 text-primary-700 dark:text-primary-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'"
                    class="border-b-2 px-3 py-2 text-sm font-medium transition"
                >{{ $label }}</button>
            @endforeach
        </div>

        {{-- Overview tab --}}
        <div x-show="tab === 'overview'" class="space-y-4">
            @php $analysis = $record->pageAnalysis; @endphp
            @if($analysis)
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="fi-section rounded-xl bg-white p-5 ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Headings</h3>
                        <ul class="mt-2 space-y-1 text-sm text-gray-800 dark:text-gray-200">
                            @foreach($analysis->headings_structure ?? [] as $h)
                                <li><span class="text-gray-500">H{{ $h['level'] ?? '?' }}</span> {{ $h['text'] ?? '' }}</li>
                            @endforeach
                            @if(empty($analysis->headings_structure))
                                <li class="text-gray-500">Geen headings gedetecteerd.</li>
                            @endif
                        </ul>
                    </div>
                    <div class="fi-section rounded-xl bg-white p-5 ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Stats</h3>
                        <dl class="mt-2 grid grid-cols-2 gap-2 text-sm text-gray-800 dark:text-gray-200">
                            <dt class="text-gray-500">Woorden</dt><dd>{{ $analysis->content_length ?? '-' }}</dd>
                            <dt class="text-gray-500">Readability</dt><dd>{{ $analysis->readability_score ?? '-' }}/100</dd>
                            @if($analysis->alt_text_coverage)
                                <dt class="text-gray-500">Images met alt</dt>
                                <dd>{{ $analysis->alt_text_coverage['with_alt'] ?? 0 }} / {{ $analysis->alt_text_coverage['total'] ?? 0 }}</dd>
                            @endif
                        </dl>
                        @if($analysis->notes)
                            <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">{{ $analysis->notes }}</p>
                        @endif
                    </div>
                </div>
            @else
                <p class="text-sm text-gray-500">Geen page-analyse beschikbaar.</p>
            @endif
        </div>

        {{-- Keywords tab --}}
        <div x-show="tab === 'keywords'" class="space-y-4">
            @foreach(['primary','secondary','longtail','lsi','gap'] as $type)
                @php $items = $record->keywords->where('type', $type); @endphp
                @if($items->isNotEmpty())
                    <div class="fi-section rounded-xl bg-white p-5 ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                        <h3 class="mb-2 text-sm font-semibold uppercase text-gray-950 dark:text-white">{{ $type }}</h3>
                        <div class="flex flex-wrap gap-2">
                            @foreach($items as $k)
                                <span class="rounded-full bg-gray-100 px-3 py-1 text-sm text-gray-800 dark:bg-white/10 dark:text-gray-100">
                                    {{ $k->keyword }}
                                    @if($k->intent)<span class="ml-1 text-xs text-gray-500">· {{ $k->intent }}</span>@endif
                                    @if($k->volume_indication)<span class="ml-1 text-xs text-gray-500">· vol: {{ $k->volume_indication }}</span>@endif
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach
            @if($record->keywords->isEmpty())
                <p class="text-sm text-gray-500">Geen keywords.</p>
            @endif
        </div>

        {{-- Meta tab --}}
        <div x-show="tab === 'meta'" class="space-y-4">
            @foreach($record->metaSuggestions as $sug)
                <div class="fi-section rounded-xl bg-white p-5 ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <code class="rounded bg-gray-100 px-2 py-0.5 text-sm font-medium text-gray-800 dark:bg-white/10 dark:text-gray-100">{{ $sug->field }}</code>
                            @if($sug->reason)<p class="mt-1 text-xs text-gray-500">{{ $sug->reason }}</p>@endif
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="checkbox" wire:model.live="selected.meta" value="{{ $sug->id }}" />
                            <x-filament::badge :color="match($sug->priority){'high'=>'danger','medium'=>'warning',default=>'gray'}">{{ $sug->priority }}</x-filament::badge>
                            <x-filament::badge :color="match($sug->status){'applied'=>'success','rejected'=>'gray','edited'=>'info','failed'=>'danger',default=>'warning'}">{{ strtoupper($sug->status) }}</x-filament::badge>
                        </div>
                    </div>
                    <div class="mt-4 grid gap-3 md:grid-cols-2">
                        <div>
                            <div class="text-xs font-medium uppercase text-gray-500">Huidig</div>
                            <div class="mt-1 whitespace-pre-wrap break-words rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm text-gray-800 dark:border-white/10 dark:bg-gray-950 dark:text-gray-200">{{ $sug->current_value ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="text-xs font-medium uppercase text-primary-600 dark:text-primary-400">Voorgesteld</div>
                            <textarea
                                wire:model="editedMeta.{{ $sug->id }}"
                                rows="3"
                                class="mt-1 block w-full resize-y rounded-lg border-gray-300 bg-white p-3 text-sm text-gray-950 dark:border-white/10 dark:bg-gray-950 dark:text-white"
                            ></textarea>
                        </div>
                    </div>
                    @if(in_array($sug->status, ['pending', 'edited']))
                        <div class="mt-3 flex gap-2">
                            <x-filament::button size="sm" wire:click="applyMetaOne({{ $sug->id }})">Toepassen</x-filament::button>
                            <x-filament::button size="sm" color="danger" wire:click="rejectMetaOne({{ $sug->id }})">Afwijzen</x-filament::button>
                        </div>
                    @endif
                </div>
            @endforeach
            @if($record->metaSuggestions->isEmpty())
                <p class="text-sm text-gray-500">Geen meta-voorstellen.</p>
            @endif
        </div>

        {{-- Blocks tab --}}
        <div x-show="tab === 'blocks'" class="space-y-4">
            @php $groups = $record->blockSuggestions->groupBy('block_index'); @endphp
            @foreach($groups as $idx => $suggs)
                <div class="fi-section rounded-xl bg-white p-5 ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="mb-3 flex items-center justify-between">
                        <code class="rounded bg-gray-100 px-2 py-0.5 text-sm font-medium text-gray-800 dark:bg-white/10 dark:text-gray-100">
                            Blok #{{ $idx ?? 'nieuw' }} · {{ $suggs->first()->block_type }}
                        </code>
                    </div>
                    @foreach($suggs as $sug)
                        <div class="mt-3 border-t border-gray-100 pt-3 dark:border-white/10">
                            <div class="flex items-start justify-between">
                                <div>
                                    <code class="text-xs text-gray-500">{{ $sug->field_key }}</code>
                                    @if($sug->is_new_block)<span class="ml-2 text-xs text-info-600">(nieuw blok)</span>@endif
                                    @if($sug->reason)<p class="mt-1 text-xs text-gray-500">{{ $sug->reason }}</p>@endif
                                </div>
                                <div class="flex items-center gap-2">
                                    <input type="checkbox" wire:model.live="selected.blocks" value="{{ $sug->id }}" />
                                    <x-filament::badge :color="match($sug->priority){'high'=>'danger','medium'=>'warning',default=>'gray'}">{{ $sug->priority }}</x-filament::badge>
                                    <x-filament::badge :color="match($sug->status){'applied'=>'success','rejected'=>'gray','edited'=>'info','failed'=>'danger',default=>'warning'}">{{ strtoupper($sug->status) }}</x-filament::badge>
                                </div>
                            </div>
                            <div class="mt-3 grid gap-3 md:grid-cols-2">
                                <div>
                                    <div class="text-xs font-medium uppercase text-gray-500">Huidig</div>
                                    <div class="mt-1 max-h-60 overflow-auto whitespace-pre-wrap break-words rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm text-gray-800 dark:border-white/10 dark:bg-gray-950 dark:text-gray-200">{{ $sug->current_value ?? '-' }}</div>
                                </div>
                                <div>
                                    <div class="text-xs font-medium uppercase text-primary-600 dark:text-primary-400">Voorgesteld</div>
                                    <textarea wire:model="editedBlocks.{{ $sug->id }}" rows="8" class="mt-1 block w-full resize-y rounded-lg border-gray-300 bg-white p-3 text-sm text-gray-950 dark:border-white/10 dark:bg-gray-950 dark:text-white"></textarea>
                                </div>
                            </div>
                            @if(in_array($sug->status, ['pending', 'edited']))
                                <div class="mt-2 flex gap-2">
                                    <x-filament::button size="sm" wire:click="applyBlockOne({{ $sug->id }})">Toepassen</x-filament::button>
                                    <x-filament::button size="sm" color="danger" wire:click="rejectBlockOne({{ $sug->id }})">Afwijzen</x-filament::button>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endforeach
            @if($record->blockSuggestions->isEmpty())
                <p class="text-sm text-gray-500">Geen blok-voorstellen.</p>
            @endif
        </div>

        {{-- FAQs tab --}}
        <div x-show="tab === 'faqs'" class="space-y-4">
            <div class="flex flex-wrap items-center gap-4 rounded-xl bg-gray-50 p-3 dark:bg-gray-900">
                <label class="text-sm font-medium">Plaatsen in:</label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="radio" wire:model.live="faqApplyTarget" value="existing" />
                    Bestaand FAQ-blok
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="radio" wire:model.live="faqApplyTarget" value="new" />
                    Nieuw FAQ-blok
                </label>
            </div>

            @foreach($record->faqSuggestions as $sug)
                <div class="fi-section rounded-xl bg-white p-5 ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="flex items-start justify-between">
                        <div>
                            @if($sug->target_keyword)<code class="text-xs text-gray-500">kw: {{ $sug->target_keyword }}</code>@endif
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="checkbox" wire:model.live="selected.faqs" value="{{ $sug->id }}" />
                            <x-filament::badge :color="match($sug->priority){'high'=>'danger','medium'=>'warning',default=>'gray'}">{{ $sug->priority }}</x-filament::badge>
                            <x-filament::badge :color="match($sug->status){'applied'=>'success','rejected'=>'gray','edited'=>'info',default=>'warning'}">{{ strtoupper($sug->status) }}</x-filament::badge>
                        </div>
                    </div>
                    <div class="mt-3 space-y-2">
                        <input wire:model="editedFaqs.{{ $sug->id }}.question" class="block w-full rounded-lg border-gray-300 bg-white p-2 text-sm text-gray-950 dark:border-white/10 dark:bg-gray-950 dark:text-white" />
                        <textarea wire:model="editedFaqs.{{ $sug->id }}.answer" rows="4" class="block w-full resize-y rounded-lg border-gray-300 bg-white p-2 text-sm text-gray-950 dark:border-white/10 dark:bg-gray-950 dark:text-white"></textarea>
                    </div>
                </div>
            @endforeach
            @if($record->faqSuggestions->isEmpty())
                <p class="text-sm text-gray-500">Geen FAQ voorstellen.</p>
            @endif
        </div>

        {{-- Structured data tab --}}
        <div x-show="tab === 'structured_data'" class="space-y-4">
            @foreach($record->structuredDataSuggestions as $sug)
                <div class="fi-section rounded-xl bg-white p-5 ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="flex items-start justify-between">
                        <div>
                            <code class="rounded bg-gray-100 px-2 py-0.5 text-sm font-medium text-gray-800 dark:bg-white/10 dark:text-gray-100">{{ $sug->schema_type }}</code>
                            @if($sug->reason)<p class="mt-1 text-xs text-gray-500">{{ $sug->reason }}</p>@endif
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="checkbox" wire:model.live="selected.structured_data" value="{{ $sug->id }}" />
                            <x-filament::badge :color="match($sug->priority){'high'=>'danger','medium'=>'warning',default=>'gray'}">{{ $sug->priority }}</x-filament::badge>
                            <x-filament::badge :color="match($sug->status){'applied'=>'success','rejected'=>'gray','edited'=>'info',default=>'warning'}">{{ strtoupper($sug->status) }}</x-filament::badge>
                        </div>
                    </div>
                    <pre class="mt-3 max-h-80 overflow-auto rounded-lg border border-gray-200 bg-gray-50 p-3 text-xs leading-relaxed text-gray-800 dark:border-white/10 dark:bg-gray-950 dark:text-gray-200">{{ $sug->json_ld }}</pre>
                </div>
            @endforeach
            @if($record->structuredDataSuggestions->isEmpty())
                <p class="text-sm text-gray-500">Geen structured data voorstellen.</p>
            @endif
        </div>

        {{-- Internal links tab --}}
        <div x-show="tab === 'internal_links'" class="space-y-4">
            @foreach($record->internalLinkSuggestions as $link)
                <div class="fi-section rounded-xl bg-white p-5 ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="flex items-start justify-between">
                        <div class="space-y-1 text-sm">
                            <div class="text-gray-950 dark:text-white"><strong>{{ $link->anchor_text }}</strong></div>
                            <div class="text-xs text-gray-500">→ <a href="{{ $link->target_url }}" target="_blank" class="text-primary-600 hover:underline">{{ $link->target_url }}</a></div>
                            <div class="text-xs text-gray-600 dark:text-gray-300">{{ $link->context_description }}</div>
                            @if($link->reason)<div class="text-xs text-gray-400">{{ $link->reason }}</div>@endif
                        </div>
                        <div class="flex items-center gap-2">
                            <x-filament::badge :color="match($link->priority){'high'=>'danger','medium'=>'warning',default=>'gray'}">{{ $link->priority }}</x-filament::badge>
                            <x-filament::badge :color="match($link->status){'acknowledged'=>'info','rejected'=>'gray',default=>'warning'}">{{ strtoupper($link->status) }}</x-filament::badge>
                        </div>
                    </div>
                    @if($link->status === 'pending')
                        <div class="mt-3 flex gap-2">
                            <x-filament::button size="sm" wire:click="acknowledgeLink({{ $link->id }})">Markeer als bekeken</x-filament::button>
                            <x-filament::button size="sm" color="danger" wire:click="rejectLink({{ $link->id }})">Afwijzen</x-filament::button>
                        </div>
                    @endif
                </div>
            @endforeach
            @if($record->internalLinkSuggestions->isEmpty())
                <p class="text-sm text-gray-500">Geen interne link voorstellen.</p>
            @endif
        </div>

        {{-- Applied logs / revert --}}
        @if(count($this->getAppliedLogs()) > 0)
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <h3 class="p-5 text-base font-semibold text-gray-950 dark:text-white">Toegepaste wijzigingen</h3>
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                    <thead class="bg-gray-50 dark:bg-white/5">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs uppercase text-gray-500">Veld/blok</th>
                            <th class="px-4 py-2 text-left text-xs uppercase text-gray-500">Wanneer</th>
                            <th class="px-4 py-2 text-right text-xs uppercase text-gray-500">Actie</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @foreach($this->getAppliedLogs() as $log)
                            <tr class="text-gray-800 dark:text-gray-200">
                                <td class="px-4 py-2 font-mono text-xs">{{ $log['field_key'] }}</td>
                                <td class="px-4 py-2">{{ $log['applied_at'] }}</td>
                                <td class="px-4 py-2 text-right">
                                    <x-filament::button size="sm" color="gray" wire:click="revertLog({{ $log['id'] }})" icon="heroicon-o-arrow-uturn-left">Revert</x-filament::button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-filament-panels::page>

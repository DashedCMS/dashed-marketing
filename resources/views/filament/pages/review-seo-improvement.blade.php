<x-filament-panels::page>
    <div class="space-y-4">
        <div class="flex justify-between">
            <div>
                <h2 class="text-xl font-semibold">Voorstel voor {{ class_basename($record->subject_type) }} #{{ $record->subject_id }}</h2>
                <p class="text-sm text-gray-600">{{ $record->analysis_summary }}</p>
            </div>
            <div class="flex gap-2">
                <x-filament::button wire:click="applyAll">Apply all pending</x-filament::button>
                <x-filament::button color="danger" wire:click="rejectAll">Reject all pending</x-filament::button>
            </div>
        </div>

        @if(! empty($record->field_proposals))
            <h3 class="font-semibold mt-4">Velden</h3>
            @foreach($record->field_proposals as $key => $newValue)
                @php
                    $status = $record->proposalStatus($key);
                    $currentValue = $record->subject?->{$key} ?? null;
                @endphp
                <div class="rounded border p-4 bg-white">
                    <div class="flex justify-between">
                        <strong>{{ $key }}</strong>
                        <span class="text-xs uppercase">{{ $status }}</span>
                    </div>
                    <div class="grid grid-cols-2 gap-4 mt-2">
                        <div>
                            <div class="text-xs text-gray-500">Huidig</div>
                            <div class="text-sm">{{ is_array($currentValue) ? json_encode($currentValue) : (string) $currentValue }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500">Voorgesteld</div>
                            <textarea wire:model="editedValues.{{ $key }}" class="w-full text-sm border-gray-300 rounded">{{ is_array($newValue) ? json_encode($newValue) : (string) $newValue }}</textarea>
                        </div>
                    </div>
                    @if($status === 'pending' || $status === 'edited')
                        <div class="flex gap-2 mt-2">
                            <x-filament::button size="xs" wire:click="applyProposal('{{ $key }}')">Apply</x-filament::button>
                            <x-filament::button size="xs" color="danger" wire:click="rejectProposal('{{ $key }}')">Reject</x-filament::button>
                        </div>
                    @endif
                </div>
            @endforeach
        @endif

        @if(! empty($record->block_proposals))
            <h3 class="font-semibold mt-4">Blokken</h3>
            @foreach($record->block_proposals as $key => $newBlock)
                @php
                    $proposalKey = "block.{$key}";
                    $status = $record->proposalStatus($proposalKey);
                    $currentBlocks = $record->subject?->customBlocks?->blocks ?? [];
                    $currentBlock = $currentBlocks[$key] ?? null;
                @endphp
                <div class="rounded border p-4 bg-white">
                    <div class="flex justify-between">
                        <strong>{{ $key }}</strong>
                        <span class="text-xs uppercase">{{ $status }}</span>
                    </div>
                    <div class="grid grid-cols-2 gap-4 mt-2">
                        <pre class="text-xs bg-gray-50 p-2 rounded">{{ json_encode($currentBlock, JSON_PRETTY_PRINT) }}</pre>
                        <pre class="text-xs bg-green-50 p-2 rounded">{{ json_encode($newBlock, JSON_PRETTY_PRINT) }}</pre>
                    </div>
                    @if($status === 'pending' || $status === 'edited')
                        <div class="flex gap-2 mt-2">
                            <x-filament::button size="xs" wire:click="applyProposal('{{ $proposalKey }}')">Apply</x-filament::button>
                            <x-filament::button size="xs" color="danger" wire:click="rejectProposal('{{ $proposalKey }}')">Reject</x-filament::button>
                        </div>
                    @endif
                </div>
            @endforeach
        @endif

        <h3 class="font-semibold mt-4">Toegepaste wijzigingen (revert)</h3>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left">
                    <th>Veld/blok</th>
                    <th>Wanneer</th>
                    <th>Door</th>
                    <th>Actie</th>
                </tr>
            </thead>
            <tbody>
                @foreach($this->getAppliedLogs() as $log)
                    <tr>
                        <td>{{ $log['field_key'] }}</td>
                        <td>{{ $log['applied_at'] }}</td>
                        <td>{{ $log['applied_by'] }}</td>
                        <td>
                            <x-filament::button size="xs" color="gray" wire:click="revertProposal({{ $log['id'] }})">Revert</x-filament::button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-filament-panels::page>

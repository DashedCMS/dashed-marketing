<x-filament-panels::page>
    @if($step === 1)
        <form wire:submit="parseHeaders" class="space-y-4">
            {{ $this->form }}
            <x-filament::button type="submit">
                Volgende
            </x-filament::button>
        </form>
    @endif

    @if($step === 2)
        <div class="space-y-4">
            <h2 class="text-xl font-semibold">Kolom-mapping</h2>
            <p class="text-sm text-gray-600">Kies per CSV-kolom welk veld hij vult.</p>

            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left">
                        <th class="py-2">CSV-kolom</th>
                        <th class="py-2">Preview</th>
                        <th class="py-2">Veld</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($headers as $i => $header)
                        <tr class="border-t">
                            <td class="py-2 font-medium">{{ $header }}</td>
                            <td class="py-2 text-gray-500">
                                @foreach($preview as $row)
                                    <div>{{ $row[$i] ?? '' }}</div>
                                @endforeach
                            </td>
                            <td class="py-2">
                                <select wire:model="mapping.{{ $i }}" class="rounded border-gray-300">
                                    @foreach($this->getMappingOptions() as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="flex items-center gap-2 mt-4">
                <label class="font-medium">Duplicaten:</label>
                <select wire:model="duplicateStrategy" class="rounded border-gray-300">
                    <option value="skip">Overslaan</option>
                    <option value="overwrite">Overschrijven</option>
                </select>
            </div>

            <div class="flex gap-2">
                <x-filament::button wire:click="submitImport">
                    Importeer
                </x-filament::button>
                <x-filament::button wire:click="$set('step', 1)" color="gray">
                    Terug
                </x-filament::button>
            </div>
        </div>
    @endif
</x-filament-panels::page>

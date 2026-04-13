<x-filament-panels::page>
    <div class="space-y-4">
        <h2 class="text-xl font-semibold">Dry-run voorbeeld</h2>
        <p class="text-sm text-gray-600">
            Controleer wat er gegenereerd gaat worden. Per keyword kun je forceren dat er een nieuwe pagina komt of de generatie overslaan.
        </p>

        @if(empty($preview))
            <div class="rounded border border-gray-200 bg-gray-50 p-4 text-gray-600">
                Geen keywords gevonden met een cluster. Cluster eerst je keywords.
            </div>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left border-b">
                        <th class="py-2">Keyword</th>
                        <th class="py-2">Cluster</th>
                        <th class="py-2">Content type</th>
                        <th class="py-2">Actie</th>
                        <th class="py-2">Match</th>
                        <th class="py-2">Score</th>
                        <th class="py-2">Overschrijf</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($preview as $row)
                        <tr class="border-b">
                            <td class="py-2 font-medium">{{ $row['keyword'] }}</td>
                            <td class="py-2">{{ $row['cluster'] }}</td>
                            <td class="py-2">{{ $row['content_type'] }}</td>
                            <td class="py-2">
                                @if($row['action'] === 'improve')
                                    <span class="text-green-700">Verbeter</span>
                                @else
                                    <span class="text-blue-700">Nieuw</span>
                                @endif
                            </td>
                            <td class="py-2">{{ $row['match_title'] ?? '—' }}</td>
                            <td class="py-2">
                                {{ $row['match_score'] ? number_format($row['match_score'], 2) : '—' }}
                            </td>
                            <td class="py-2 space-y-1">
                                <label class="block">
                                    <input type="checkbox" wire:model="overrides.{{ $row['id'] }}.force_create">
                                    forceer nieuw
                                </label>
                                <label class="block">
                                    <input type="checkbox" wire:model="overrides.{{ $row['id'] }}.skip">
                                    skip
                                </label>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="flex gap-2">
                <x-filament::button wire:click="confirmGeneration">
                    Bevestig en genereer
                </x-filament::button>
            </div>
        @endif
    </div>
</x-filament-panels::page>

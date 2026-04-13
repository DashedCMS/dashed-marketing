<x-filament-panels::page>
    <div class="space-y-4">
        <h2 class="text-xl font-semibold">Dry-run voorbeeld ({{ $locale }})</h2>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left">
                    <th>Keyword</th>
                    <th>Cluster</th>
                    <th>Content type</th>
                    <th>Actie</th>
                    <th>Match</th>
                    <th>Score</th>
                    <th>Overschrijf</th>
                </tr>
            </thead>
            <tbody>
                @foreach($preview as $row)
                    <tr>
                        <td class="py-2">{{ $row['keyword'] }}</td>
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
                        <td class="py-2">{{ $row['match_score'] ? number_format($row['match_score'], 2) : '—' }}</td>
                        <td class="py-2">
                            <label><input type="checkbox" wire:model="overrides.{{ $row['id'] }}.force_create"> forceer nieuw</label><br>
                            <label><input type="checkbox" wire:model="overrides.{{ $row['id'] }}.skip"> skip</label>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <x-filament::button wire:click="confirmGeneration">Bevestig en genereer</x-filament::button>
    </div>
</x-filament-panels::page>

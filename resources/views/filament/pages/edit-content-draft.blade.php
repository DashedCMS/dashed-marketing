<x-filament-panels::page>
    <div class="grid grid-cols-12 gap-4">
        <div class="col-span-9 space-y-4">
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-semibold">{{ $record->keyword }}</h2>
                <x-filament::button wire:click="addSection">+ Nieuwe sectie</x-filament::button>
            </div>

            @foreach($sections as $index => $section)
                <div class="rounded border p-4 bg-white" wire:key="section-{{ $section['id'] }}">
                    <input
                        class="w-full text-lg font-semibold border-0 focus:ring-0"
                        wire:model.live.debounce.500ms="sections.{{ $index }}.heading"
                        wire:change="autosave"
                    />
                    <textarea
                        class="w-full min-h-[200px] border-0 focus:ring-0"
                        wire:model.live.debounce.500ms="sections.{{ $index }}.body"
                        wire:change="autosave"
                    ></textarea>
                    <div class="flex gap-2 pt-2 border-t mt-2">
                        <x-filament::button size="xs" wire:click="regenerateSection('{{ $section['id'] }}')">
                            Regenereer sectie
                        </x-filament::button>
                        <x-filament::button size="xs" color="gray" wire:click="moveSection('{{ $section['id'] }}', -1)">&uarr;</x-filament::button>
                        <x-filament::button size="xs" color="gray" wire:click="moveSection('{{ $section['id'] }}', 1)">&darr;</x-filament::button>
                        <x-filament::button size="xs" color="danger" wire:click="removeSection('{{ $section['id'] }}')">Verwijder</x-filament::button>
                    </div>
                </div>
            @endforeach
        </div>

        <aside class="col-span-3 space-y-4">
            <div class="rounded border p-4 bg-gray-50">
                <h3 class="font-semibold mb-2">Interne link kandidaten</h3>
                <ul class="text-xs space-y-1">
                    @foreach($linkCandidates as $link)
                        <li>
                            <span class="text-gray-500">[{{ $link['type'] }}]</span>
                            <a href="{{ $link['url'] }}" class="text-blue-600">{{ $link['title'] }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </aside>
    </div>
</x-filament-panels::page>

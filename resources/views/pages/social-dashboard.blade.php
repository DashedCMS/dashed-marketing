<x-filament-panels::page>
    @php
        $stats = $this->getWeekStats();
        $pillarMix = $this->getPillarMix();
        $holidays = $this->getUpcomingHolidays();
    @endphp

    {{-- Week stats --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <x-filament::section>
            <div class="text-center">
                <div class="text-3xl font-bold text-success-600">{{ $stats['posted'] }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">Gepost deze week</div>
            </div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-center">
                <div class="text-3xl font-bold text-primary-600">{{ $stats['scheduled'] }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">Ingepland deze week</div>
            </div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-center">
                <div class="text-3xl font-bold text-danger-600">{{ $stats['overdue'] }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">Achterstallig</div>
            </div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-center">
                <div class="text-3xl font-bold text-warning-600">{{ $stats['concepts'] }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">Concepten</div>
            </div>
        </x-filament::section>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        {{-- Pillar mix --}}
        <x-filament::section heading="Pijler mix">
            @if(empty($pillarMix))
                <p class="text-sm text-gray-500">Nog geen pijlers geconfigureerd.</p>
            @else
                <div class="space-y-3">
                    @foreach($pillarMix as $pillar)
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="font-medium text-gray-700 dark:text-gray-300">{{ $pillar['name'] }}</span>
                                <span class="text-gray-500">
                                    {{ $pillar['actual'] }}% <span class="text-gray-400">(doel: {{ $pillar['target'] }}%)</span>
                                </span>
                            </div>
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 relative">
                                {{-- Target indicator --}}
                                @if($pillar['target'] > 0)
                                    <div
                                        class="absolute top-0 h-2 w-0.5 bg-gray-500 dark:bg-gray-400"
                                        style="left: {{ min($pillar['target'], 100) }}%"
                                    ></div>
                                @endif
                                {{-- Actual bar --}}
                                <div
                                    class="h-2 rounded-full transition-all"
                                    style="width: {{ min($pillar['actual'], 100) }}%; background-color: {{ $pillar['color'] }}"
                                ></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-filament::section>

        {{-- Upcoming holidays --}}
        <x-filament::section heading="Komende feestdagen (30 dagen)">
            @if(empty($holidays))
                <p class="text-sm text-gray-500">Geen feestdagen in de komende 30 dagen.</p>
            @else
                <div class="space-y-2">
                    @foreach($holidays as $holiday)
                        <div class="flex items-center justify-between p-2 rounded-lg bg-gray-50 dark:bg-gray-800">
                            <div>
                                <div class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ $holiday['name'] }}</div>
                                <div class="text-xs text-gray-500">{{ $holiday['date'] }} &bull; {{ $holiday['country'] }}</div>
                            </div>
                            <div class="text-xs font-medium {{ $holiday['days_until'] <= 7 ? 'text-danger-600' : 'text-gray-500' }}">
                                over {{ $holiday['days_until'] }} dagen
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>

<x-filament-widgets::widget>
    <x-filament::section>
        <div x-data="{ dragging: null }" class="space-y-4">
            {{-- Header --}}
            <div class="flex items-center justify-between">
                <button wire:click="previousMonth" class="p-2 rounded hover:bg-gray-100 dark:hover:bg-gray-700">
                    <x-heroicon-o-chevron-left class="w-5 h-5" />
                </button>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ \Illuminate\Support\Carbon::createFromDate($currentYear, $currentMonth, 1)->translatedFormat('F Y') }}
                </h2>
                <button wire:click="nextMonth" class="p-2 rounded hover:bg-gray-100 dark:hover:bg-gray-700">
                    <x-heroicon-o-chevron-right class="w-5 h-5" />
                </button>
            </div>

            {{-- Day headers --}}
            <div class="grid grid-cols-7 gap-1">
                @foreach(['Ma', 'Di', 'Wo', 'Do', 'Vr', 'Za', 'Zo'] as $day)
                    <div class="text-center text-xs font-medium text-gray-500 dark:text-gray-400 py-1">{{ $day }}</div>
                @endforeach
            </div>

            {{-- Calendar grid --}}
            <div class="grid gap-1">
                @foreach($this->getCalendarData() as $week)
                    <div class="grid grid-cols-7 gap-1">
                        @foreach($week as $day)
                            <div
                                class="min-h-[80px] p-1 rounded border {{ $day['isCurrentMonth'] ? 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700' : 'bg-gray-50 dark:bg-gray-900 border-gray-100 dark:border-gray-800' }} {{ $day['isToday'] ? 'ring-2 ring-primary-500' : '' }}"
                                x-on:dragover.prevent
                                x-on:drop.prevent="$wire.reschedulePost(dragging, '{{ $day['date'] }}')"
                            >
                                <div class="text-xs font-medium mb-1 {{ $day['isCurrentMonth'] ? 'text-gray-700 dark:text-gray-300' : 'text-gray-400 dark:text-gray-600' }} {{ $day['isToday'] ? 'text-primary-600 font-bold' : '' }}">
                                    {{ $day['day'] }}
                                </div>
                                @foreach($day['events'] as $event)
                                    <a
                                        href="{{ $event['edit_url'] }}"
                                        draggable="true"
                                        x-on:dragstart="dragging = {{ $event['id'] }}"
                                        x-on:dragend="dragging = null"
                                        class="block text-xs p-1 mb-0.5 rounded truncate cursor-grab
                                            {{ match($event['color']) {
                                                'gray' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
                                                'warning' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300',
                                                'info' => 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300',
                                                'purple' => 'bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300',
                                                'success' => 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
                                                'danger' => 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
                                                default => 'bg-gray-100 text-gray-700',
                                            } }}"
                                        title="{{ $event['platform'] }}: {{ $event['caption'] }}"
                                    >
                                        {{ $event['time'] }} {{ $event['platform'] }}
                                    </a>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>

<x-filament-panels::page>
    @php
        $stats = $this->getWeekStats();
        $pillarMix = $this->getPillarMix();
        $holidays = $this->getUpcomingHolidays();
        $upcomingPosts = $this->getUpcomingPosts();
        $overduePosts = $this->getOverduePosts();
        $concepts = $this->getConceptsNeedingAttention();
        $recentlyPosted = $this->getRecentlyPosted();
        $channelBreakdown = $this->getChannelBreakdown();
        $pendingIdeas = $this->getPendingIdeasCount();
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

    {{-- Upcoming scheduled posts --}}
    <div class="mb-6">
        <x-filament::section heading="Aankomende posts" description="De eerstvolgende 10 ingeplande of goedgekeurde posts.">
            @if(empty($upcomingPosts))
                <p class="text-sm text-gray-500">Geen aankomende posts. Plan er een in via Social posts.</p>
            @else
                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($upcomingPosts as $post)
                        <a href="{{ $post['edit_url'] }}" class="flex items-start gap-3 py-3 hover:bg-gray-50 dark:hover:bg-gray-800 -mx-2 px-2 rounded-lg transition">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-xs font-medium text-primary-600">
                                        {{ $post['scheduled_at'] }}
                                    </span>
                                    <span class="text-xs text-gray-400">
                                        @if($post['days_until'] === 0)
                                            vandaag
                                        @elseif($post['days_until'] === 1)
                                            morgen
                                        @else
                                            over {{ $post['days_until'] }} dagen
                                        @endif
                                    </span>
                                    @if(! $post['has_image'])
                                        <span class="text-xs px-1.5 py-0.5 rounded bg-warning-100 text-warning-700 dark:bg-warning-900 dark:text-warning-300">geen afbeelding</span>
                                    @endif
                                </div>
                                <div class="text-sm text-gray-800 dark:text-gray-200 line-clamp-2">{{ $post['caption'] }}</div>
                                <div class="flex gap-1 mt-1">
                                    @foreach($post['channels'] as $channel)
                                        <span class="text-[10px] uppercase tracking-wide px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">{{ $channel }}</span>
                                    @endforeach
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </x-filament::section>
    </div>

    {{-- Attention needed --}}
    @if(! empty($overduePosts) || ! empty($concepts))
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
            @if(! empty($overduePosts))
                <x-filament::section heading="Achterstallig" description="Posts waarvan de geplande datum al voorbij is.">
                    <div class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach($overduePosts as $post)
                            <a href="{{ $post['edit_url'] }}" class="block py-2 -mx-2 px-2 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-lg transition">
                                <div class="text-xs font-medium text-danger-600 mb-0.5">{{ $post['scheduled_at'] }}</div>
                                <div class="text-sm text-gray-800 dark:text-gray-200 line-clamp-1">{{ $post['caption'] }}</div>
                            </a>
                        @endforeach
                    </div>
                </x-filament::section>
            @endif

            @if(! empty($concepts))
                <x-filament::section heading="Concepten die aandacht nodig hebben" description="Laatst bewerkt eerst.">
                    <div class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach($concepts as $post)
                            <a href="{{ $post['edit_url'] }}" class="block py-2 -mx-2 px-2 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-lg transition">
                                <div class="flex items-center gap-2 mb-0.5">
                                    <span class="text-xs text-gray-500">{{ $post['updated_at'] }}</span>
                                    @if($post['missing_schedule'])
                                        <span class="text-[10px] uppercase tracking-wide px-1.5 py-0.5 rounded bg-warning-100 text-warning-700 dark:bg-warning-900 dark:text-warning-300">niet ingepland</span>
                                    @endif
                                    @if($post['missing_image'])
                                        <span class="text-[10px] uppercase tracking-wide px-1.5 py-0.5 rounded bg-warning-100 text-warning-700 dark:bg-warning-900 dark:text-warning-300">geen afbeelding</span>
                                    @endif
                                </div>
                                <div class="text-sm text-gray-800 dark:text-gray-200 line-clamp-1">{{ $post['caption'] }}</div>
                            </a>
                        @endforeach
                    </div>
                </x-filament::section>
            @endif
        </div>
    @endif

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

        {{-- Channel breakdown this month --}}
        <x-filament::section heading="Kanalen deze maand" description="Aantal posts ingepland of gepost per kanaal deze maand.">
            @if(empty($channelBreakdown))
                <p class="text-sm text-gray-500">Geen posts deze maand.</p>
            @else
                @php $maxChannel = max(array_column($channelBreakdown, 'count')); @endphp
                <div class="space-y-2">
                    @foreach($channelBreakdown as $channel)
                        <div>
                            <div class="flex justify-between text-xs mb-1">
                                <span class="text-gray-700 dark:text-gray-300">{{ $channel['label'] }}</span>
                                <span class="text-gray-500">{{ $channel['count'] }}</span>
                            </div>
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                <div class="h-2 rounded-full bg-primary-500" style="width: {{ $maxChannel > 0 ? round(($channel['count'] / $maxChannel) * 100) : 0 }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-filament::section>

        {{-- Recently posted --}}
        <x-filament::section heading="Laatst gepost" description="De laatste 5 gepubliceerde posts.">
            @if(empty($recentlyPosted))
                <p class="text-sm text-gray-500">Nog geen posts gepubliceerd.</p>
            @else
                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($recentlyPosted as $post)
                        <div class="py-2 flex items-start gap-2">
                            <div class="flex-1 min-w-0">
                                <div class="text-xs text-gray-500 mb-0.5">{{ $post['posted_at'] }}</div>
                                <div class="text-sm text-gray-800 dark:text-gray-200 line-clamp-1">{{ $post['caption'] }}</div>
                            </div>
                            @if($post['post_url'])
                                <a href="{{ $post['post_url'] }}" target="_blank" rel="noopener" class="text-xs text-primary-600 hover:text-primary-700 shrink-0">bekijk</a>
                            @endif
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

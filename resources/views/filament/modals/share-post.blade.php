@php
    $hashtags = $record->hashtags ?? [];
    $hashtagLine = is_array($hashtags) && count($hashtags)
        ? collect($hashtags)->map(fn ($t) => str_starts_with($t, '#') ? $t : '#'.$t)->implode(' ')
        : '';
    $fullShareText = trim(($record->caption ?? '').($hashtagLine ? "\n\n".$hashtagLine : ''));
    $imageUrl = $record->image_path ? asset($record->image_path) : null;
@endphp

<div class="space-y-6" x-data="{
    copied: null,
    copy(key, text) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text);
        } else {
            const ta = document.createElement('textarea');
            ta.value = text;
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            ta.remove();
        }
        this.copied = key;
        setTimeout(() => { if (this.copied === key) this.copied = null; }, 1800);
    }
}">
    {{-- Platform + status --}}
    <div class="flex items-center gap-2 text-sm">
        <span class="inline-flex items-center rounded-md bg-primary-50 px-2 py-1 font-medium text-primary-700 dark:bg-primary-500/10 dark:text-primary-400">
            {{ $record->platform_label ?? $record->platform }}
        </span>
        <span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-1 font-medium text-gray-700 dark:bg-gray-500/10 dark:text-gray-300">
            {{ $record->status_label ?? $record->status }}
        </span>
    </div>

    {{-- Caption + hashtags (combined, copy as one) --}}
    <div>
        <div class="mb-2 flex items-center justify-between">
            <label class="text-sm font-semibold text-gray-950 dark:text-white">
                Caption + hashtags
            </label>
            <button
                type="button"
                class="text-xs font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400"
                x-on:click="copy('full', @js($fullShareText))"
            >
                <span x-show="copied !== 'full'">Kopieer alles</span>
                <span x-show="copied === 'full'" x-cloak>Gekopieerd!</span>
            </button>
        </div>
        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm leading-relaxed text-gray-900 dark:border-white/10 dark:bg-white/5 dark:text-gray-100">
            <div class="whitespace-pre-wrap">{{ $record->caption }}</div>
            @if ($hashtagLine)
                <div class="mt-4 whitespace-pre-wrap text-primary-600 dark:text-primary-400">{{ $hashtagLine }}</div>
            @endif
        </div>
    </div>

    {{-- Image --}}
    @if ($imageUrl)
        <div>
            <div class="mb-2 flex items-center justify-between">
                <label class="text-sm font-semibold text-gray-950 dark:text-white">Afbeelding</label>
                <div class="flex items-center gap-3 text-xs font-medium">
                    <a
                        href="{{ $imageUrl }}"
                        target="_blank"
                        rel="noopener"
                        class="text-primary-600 hover:text-primary-500 dark:text-primary-400"
                    >
                        Open in nieuw tabblad
                    </a>
                    <a
                        href="{{ $imageUrl }}"
                        download
                        class="inline-flex items-center gap-1 rounded-md bg-primary-600 px-2.5 py-1 text-white hover:bg-primary-500"
                    >
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                        Download
                    </a>
                </div>
            </div>
            <div class="overflow-hidden rounded-lg border border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-white/5">
                <img src="{{ $imageUrl }}" alt="{{ $record->alt_text ?? '' }}" class="mx-auto max-h-[420px] w-auto object-contain" />
            </div>
            @if ($record->alt_text)
                <p class="mt-2 text-xs italic text-gray-500 dark:text-gray-400">Alt: {{ $record->alt_text }}</p>
            @endif
        </div>
    @else
        <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 px-4 py-6 text-center text-sm text-gray-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-400">
            Nog geen afbeelding gegenereerd. Gebruik "Genereer afbeelding met AI" om er één te maken.
        </div>
    @endif

    {{-- Post URL --}}
    <div>
        <div class="mb-2 flex items-center justify-between">
            <label class="text-sm font-semibold text-gray-950 dark:text-white">Post URL</label>
            @if ($record->post_url)
                <button
                    type="button"
                    class="text-xs font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400"
                    x-on:click="copy('url', @js($record->post_url))"
                >
                    <span x-show="copied !== 'url'">Kopieer link</span>
                    <span x-show="copied === 'url'" x-cloak>Gekopieerd!</span>
                </button>
            @endif
        </div>
        @if ($record->post_url)
            <a
                href="{{ $record->post_url }}"
                target="_blank"
                rel="noopener"
                class="block truncate rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-primary-600 hover:text-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-primary-400"
            >
                {{ $record->post_url }}
            </a>
        @else
            <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 px-4 py-3 text-sm text-gray-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-400">
                Nog geen URL bekend. Vul deze in nadat de post live staat via "Markeer als gepost".
            </div>
        @endif
    </div>
</div>

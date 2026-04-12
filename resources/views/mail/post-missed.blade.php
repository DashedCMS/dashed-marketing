<x-mail::message>
# Post nog niet geplaatst

Hoi,

De volgende post voor **{{ $siteName }}** stond gisteren gepland maar heeft nog steeds de status **"Ingepland"**.

**Platform:** {{ $post->platform_label }}

**Caption:**
> {{ str($post->caption)->limit(200) }}

**Gepland op:** {{ $post->scheduled_at?->format('d-m-Y H:i') ?? '—' }}

Is de post toch geplaatst? Markeer hem dan als gepost in het dashboard.

<x-mail::button url="{{ config('app.url') . '/admin' }}">
Bekijk de post
</x-mail::button>

Met vriendelijke groet,<br>
{{ $siteName }}
</x-mail::message>

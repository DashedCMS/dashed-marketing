<x-mail::message>
# Posts te plaatsen vandaag

Hoi,

Je hebt vandaag **{{ $posts->count() }} {{ $posts->count() === 1 ? 'post' : 'posts' }}** gepland staan voor **{{ $siteName }}**.

<x-mail::table>
| Platform | Caption | Geplande tijd |
|----------|---------|---------------|
@foreach ($posts as $post)
| {{ $post->platform_label }} | {{ str($post->caption)->limit(60) }} | {{ $post->scheduled_at?->format('H:i') ?? '-' }} |
@endforeach
</x-mail::table>

Vergeet niet om de posts op tijd te plaatsen!

<x-mail::button url="{{ config('app.url') . '/admin' }}">
Ga naar het dashboard
</x-mail::button>

Met vriendelijke groet,<br>
{{ $siteName }}
</x-mail::message>

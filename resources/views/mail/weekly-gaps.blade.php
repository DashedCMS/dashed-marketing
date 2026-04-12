<x-mail::message>
# Lege dagen in je social media planning

Hoi,

Er zijn **{{ $emptyDates->count() }} {{ $emptyDates->count() === 1 ? 'dag' : 'dagen' }}** zonder geplande posts de komende 2 weken voor **{{ $siteName }}**.

**Lege dagen:**

@foreach ($emptyDates as $date)
- {{ \Carbon\Carbon::parse($date)->locale('nl')->isoFormat('dddd D MMMM') }}
@endforeach

Vul deze dagen in met nieuwe content om je aanwezigheid op social media consistent te houden.

<x-mail::button url="{{ config('app.url') . '/admin' }}">
Plan nieuwe posts
</x-mail::button>

Met vriendelijke groet,<br>
{{ $siteName }}
</x-mail::message>

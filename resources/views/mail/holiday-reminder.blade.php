<x-mail::message>
# Feestdag herinnering: {{ $holiday->name }}

Hoi,

@php
    $daysUntil = now()->startOfDay()->diffInDays($holiday->date->startOfDay());
    $when = $daysUntil === 0 ? 'vandaag' : 'over ' . $daysUntil . ' ' . ($daysUntil === 1 ? 'dag' : 'dagen');
@endphp

**{{ $holiday->name }}** is **{{ $when }}** ({{ $holiday->date->format('d-m-Y') }}).

@if ($holiday->description)
{{ $holiday->description }}

@endif
Vergeet niet om een passende social media post te maken voor **{{ $siteName }}** rondom deze feestdag!

<x-mail::button url="{{ config('app.url') . '/admin' }}">
Maak een post aan
</x-mail::button>

Met vriendelijke groet,<br>
{{ $siteName }}
</x-mail::message>

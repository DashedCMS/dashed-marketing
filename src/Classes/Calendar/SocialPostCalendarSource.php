<?php

namespace Dashed\DashedMarketing\Classes\Calendar;

use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Dashed\DashedMarketing\Models\SocialPost;
use Dashed\DashedMarketing\Filament\Resources\SocialPostResource;

/**
 * Socialposts op de contentkalender van dashed-core: een geposte post op
 * zijn postdatum, een ingeplande post op zijn plandatum. Concepten zonder
 * datum en het archief staan er niet op.
 */
class SocialPostCalendarSource
{
    public static function register(): void
    {
        if (! class_exists(\Dashed\DashedCore\Classes\Calendar\ContentCalendar::class)) {
            return;
        }

        cms()->builder(\Dashed\DashedCore\Classes\Calendar\ContentCalendar::BUILDER, [
            'social-posts' => [
                'label' => __('Socialposts'),
                'color' => '#db2777',
                'events' => fn (Carbon $from, Carbon $to, ?string $siteId) => static::events($from, $to, $siteId),
            ],
        ]);
    }

    /**
     * @return array<int, \Dashed\DashedCore\Classes\Calendar\CalendarEvent>
     */
    public static function events(Carbon $from, Carbon $to, ?string $siteId): array
    {
        return SocialPost::query()
            ->where('status', '!=', 'archived')
            ->where(fn ($q) => $q->whereBetween('posted_at', [$from, $to])
                ->orWhere(fn ($q) => $q->whereNull('posted_at')->whereBetween('scheduled_at', [$from, $to])))
            ->when($siteId, fn ($q) => $q->where(fn ($q) => $q->whereNull('site_id')->orWhere('site_id', $siteId)))
            ->limit(300)
            ->get()
            ->map(function (SocialPost $post) {
                $moment = Carbon::parse($post->posted_at ?? $post->scheduled_at);
                $caption = Str::limit(trim(strip_tags((string) $post->caption)), 50) ?: '#'.$post->getKey();

                return new \Dashed\DashedCore\Classes\Calendar\CalendarEvent(
                    source: 'social-posts',
                    title: $post->posted_at
                        ? __('Social: :tekst', ['tekst' => $caption])
                        : __('Social: :tekst (ingepland)', ['tekst' => $caption]),
                    start: $moment,
                    end: $moment->copy()->endOfDay(),
                    url: rescue(fn () => SocialPostResource::getUrl('edit', ['record' => $post]), null, report: false),
                );
            })
            ->all();
    }
}

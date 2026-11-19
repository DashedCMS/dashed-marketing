<?php

namespace Dashed\DashedMarketing\Services\AutoDrafts;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Database\Eloquent\Model;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedMarketing\Models\SocialPost;
use Dashed\DashedMarketing\Models\SocialChannel;
use Dashed\DashedMarketing\Jobs\GenerateAutoSocialDraftJob;

/**
 * Een CONCEPT social post zodra een product, productgroep of artikel voor het
 * eerst gepubliceerd wordt. Nooit publiceren: de post staat op `concept`
 * zonder geplande datum, en een beheerder krijgt een melding om hem na te
 * lopen. Standaard uit, per site aan te zetten bij Instellingen, Social media.
 *
 * "Gepubliceerd" is de overgang naar `public = true`: bij aanmaken of bij het
 * omzetten van de schakelaar, en alleen als de startdatum niet in de toekomst
 * ligt. Per item hooguit één automatisch concept, ooit, en per site hooguit
 * `dashed-marketing.auto_drafts.max_per_day` per dag, zodat een import van
 * duizend producten geen duizend AI-aanroepen wordt.
 */
class AutoSocialDrafts
{
    public const SETTING_ENABLED = 'social_auto_drafts_enabled';

    public const SETTING_TYPES = 'social_auto_draft_types';

    public const SETTING_CHANNELS = 'social_auto_draft_channels';

    /** @return array<string, array{class: class-string, label: string}> */
    public static function types(): array
    {
        $types = [
            'product' => ['class' => 'Dashed\\DashedEcommerceCore\\Models\\Product', 'label' => __('Producten')],
            'product_group' => ['class' => 'Dashed\\DashedEcommerceCore\\Models\\ProductGroup', 'label' => __('Productgroepen')],
            'article' => ['class' => 'Dashed\\DashedArticles\\Models\\Article', 'label' => __('Artikelen')],
        ];

        return array_filter($types, fn (array $type) => class_exists($type['class']));
    }

    public static function listen(): void
    {
        foreach (self::types() as $key => $type) {
            Event::listen('eloquent.saved: ' . $type['class'], function (Model $model) use ($key): void {
                rescue(fn () => self::handleSaved($model, $key), null, true);
            });
        }
    }

    public static function enabled($siteId): bool
    {
        return (bool) Customsetting::get(self::SETTING_ENABLED, $siteId);
    }

    /** @return array<int, string> */
    public static function enabledTypes($siteId): array
    {
        return self::jsonList(Customsetting::get(self::SETTING_TYPES, $siteId));
    }

    /** @return array<int, string> */
    public static function channels($siteId): array
    {
        $channels = self::jsonList(Customsetting::get(self::SETTING_CHANNELS, $siteId))
            ?: self::jsonList(Customsetting::get('social_channels', $siteId));

        if ($channels) {
            return $channels;
        }

        // Niets gekozen: de actieve kanalen van de site die een gewone post kennen.
        return SocialChannel::query()->withoutGlobalScopes()
            ->where('site_id', $siteId)
            ->where('is_active', true)
            ->orderBy('order')
            ->get()
            ->filter(fn (SocialChannel $channel) => in_array('post', (array) $channel->accepted_types, true))
            ->pluck('slug')
            ->values()
            ->all();
    }

    public static function handleSaved(Model $model, string $type): void
    {
        if (! self::justPublished($model) || ! self::isLeadItem($model, $type)) {
            return;
        }

        foreach (self::sitesOf($model) as $siteId) {
            if (! self::enabled($siteId) || ! in_array($type, self::enabledTypes($siteId), true)) {
                continue;
            }

            if (self::alreadyHasPost($model)) {
                return;
            }

            // Eén keer per item: ook twee keer opslaan vlak achter elkaar,
            // voordat de job de post heeft gemaakt, mag geen tweede geven.
            if (! Cache::add(self::lockKey($model), 1, now()->addDay())) {
                return;
            }

            if (! self::withinDailyLimit($siteId)) {
                return;
            }

            GenerateAutoSocialDraftJob::dispatch($type, get_class($model), $model->getKey(), (string) $siteId);

            return;
        }
    }

    public static function justPublished(Model $model): bool
    {
        if (! ($model->public ?? false)) {
            return false;
        }

        if (! $model->wasRecentlyCreated && ! $model->wasChanged('public')) {
            return false;
        }

        // Na een insert is wasChanged() leeg, dus wasRecentlyCreated telt
        // alleen bij de eerste save; daarna moet public echt omgezet zijn.
        if ($model->wasRecentlyCreated && $model->getChanges() !== [] && ! $model->wasChanged('public')) {
            return false;
        }

        if (method_exists($model, 'trashed') && $model->trashed()) {
            return false;
        }

        $start = $model->start_date ?? null;

        return ! $start || Carbon::parse($start)->lte(now());
    }

    /**
     * Een variant in een groep met meer producten krijgt geen eigen post:
     * daar is de productgroep voor, anders levert één nieuwe groep een post
     * per maat op.
     */
    public static function isLeadItem(Model $model, string $type): bool
    {
        if ($type !== 'product' || ! ($model->product_group_id ?? null)) {
            return true;
        }

        return $model->newQuery()->where('product_group_id', $model->product_group_id)->count() <= 1;
    }

    public static function alreadyHasPost(Model $model): bool
    {
        return SocialPost::query()->withoutGlobalScopes()
            ->where('subject_type', get_class($model))
            ->where('subject_id', $model->getKey())
            ->exists();
    }

    public static function lockKey(Model $model): string
    {
        return 'marketing:auto-social-draft:' . md5(get_class($model)) . ':' . $model->getKey();
    }

    protected static function withinDailyLimit($siteId): bool
    {
        $max = (int) config('dashed-marketing.auto_drafts.max_per_day', 10);

        if ($max <= 0) {
            return true;
        }

        $key = 'marketing:auto-social-drafts:' . $siteId . ':' . now()->toDateString();
        Cache::add($key, 0, now()->addDays(2));

        return Cache::increment($key) <= $max;
    }

    /** @return array<int, string> */
    protected static function sitesOf(Model $model): array
    {
        $siteIds = $model->site_ids ?? null;

        if (is_string($siteIds)) {
            $siteIds = json_decode($siteIds, true);
        }

        if (is_array($siteIds) && $siteIds !== []) {
            return array_map('strval', array_values($siteIds));
        }

        return [(string) ($model->site_id ?? Sites::getActive())];
    }

    /** @return array<int, string> */
    protected static function jsonList(mixed $value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        return is_array($value) ? array_values(array_filter($value, 'is_string')) : [];
    }
}

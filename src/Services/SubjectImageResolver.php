<?php

namespace Dashed\DashedMarketing\Services;

use Spatie\MediaLibrary\HasMedia;
use Illuminate\Database\Eloquent\Model;

class SubjectImageResolver
{
    /**
     * Return a list of public image URLs for a visitable model.
     * Keys and values are both URLs so Filament Select can render them directly.
     *
     * @return array<string, string>
     */
    public function collect(?Model $model): array
    {
        if (! $model) {
            return [];
        }

        $urls = array_merge(
            $this->fromImagesAttribute($model),
            $this->fromImageAttribute($model),
            $this->fromSpatieMediaLibrary($model),
        );

        $urls = array_values(array_unique(array_filter($urls)));

        return array_combine($urls, $urls) ?: [];
    }

    private function fromImagesAttribute(Model $model): array
    {
        if (! array_key_exists('images', $model->getAttributes()) && ! $model->hasCast('images')) {
            return [];
        }

        $raw = $model->images ?? null;
        if (! is_array($raw) || empty($raw)) {
            return [];
        }

        return array_filter(array_map(fn ($id) => $this->resolveUrl($id), $raw));
    }

    private function fromImageAttribute(Model $model): array
    {
        if (! array_key_exists('image', $model->getAttributes())) {
            return [];
        }

        $url = $this->resolveUrl($model->image);

        return $url ? [$url] : [];
    }

    private function fromSpatieMediaLibrary(Model $model): array
    {
        if (! interface_exists(HasMedia::class) || ! $model instanceof HasMedia) {
            return [];
        }

        // Een privé of beschermd item gaat niet als adres naar een externe dienst.
        if ($this->isProtectedItem($model)) {
            return [];
        }

        return $model->getMedia()
            ->reject(fn ($media) => class_exists(\Dashed\DashedFiles\Classes\Access\MediaStorage::class)
                && \Dashed\DashedFiles\Classes\Access\MediaStorage::isPrivateDisk($media->disk))
            ->map(fn ($media) => $media->getFullUrl())
            ->filter()
            ->values()
            ->all();
    }

    private function resolveUrl(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        if (is_array($value)) {
            $value = $value[0] ?? null;
            if (! $value) {
                return null;
            }
        }

        // Een geplakt adres mag, behalve als het naar een beschermd bestand
        // van de eigen bibliotheek wijst.
        if (is_string($value) && ! ctype_digit($value)) {
            return self::isProtectedUrl($value) ? null : $value;
        }

        if ($this->isProtectedMediaId($value)) {
            return null;
        }

        if (function_exists('mediaHelper')) {
            $resolved = mediaHelper()->getSingleMedia($value, 'original');

            if (is_string($resolved) && $resolved !== '') {
                return $resolved;
            }

            if (is_object($resolved) && isset($resolved->url)) {
                return $resolved->url;
            }
        }

        return null;
    }

    /**
     * Een media-id waarvan dashed-files zegt dat hij niet naar een externe
     * AI mag (privé, chatbijlage, map chat of orders). Zonder MediaPrivacy
     * (een oudere dashed-files) bestaat dat begrip niet en gaat alles door,
     * zoals voorheen. Kan de toets niet gedaan worden, dan geldt het item als
     * beschermd: bij twijfel gaat er niets naar buiten.
     */
    private function isProtectedMediaId(mixed $value): bool
    {
        if (! class_exists(\Dashed\DashedFiles\Classes\MediaPrivacy::class) || ! is_numeric($value)) {
            return false;
        }

        return rescue(function () use ($value) {
            $item = \Dashed\DashedFiles\Models\MediaItem::withoutGlobalScopes()->find((int) $value);

            return $item !== null && \Dashed\DashedFiles\Classes\MediaPrivacy::isProtected($item);
        }, true, false);
    }

    /**
     * Of een adres naar een privé of beschermd bestand van de eigen
     * mediabibliotheek wijst. Een extern adres is vrij. Zonder die toets (een
     * oudere dashed-files) gaat het adres door zoals voorheen. Kost een paar
     * query's: alleen voor een losse actie, niet voor een lijst.
     */
    public static function isProtectedUrl(?string $url): bool
    {
        $url = trim((string) $url);

        if ($url === ''
            || ! class_exists(\Dashed\DashedFiles\Classes\MediaPrivacy::class)
            || ! method_exists(\Dashed\DashedFiles\Classes\MediaPrivacy::class, 'isProtectedUrl')) {
            return false;
        }

        return (bool) rescue(fn () => \Dashed\DashedFiles\Classes\MediaPrivacy::isProtectedUrl($url), true, false);
    }

    private function isProtectedItem(Model $model): bool
    {
        if (! class_exists(\Dashed\DashedFiles\Classes\MediaPrivacy::class) || ! $model instanceof \Dashed\DashedFiles\Models\MediaItem) {
            return false;
        }

        return rescue(fn () => \Dashed\DashedFiles\Classes\MediaPrivacy::isProtected($model), true, false);
    }
}

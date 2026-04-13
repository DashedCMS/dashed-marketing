<?php

namespace Dashed\DashedMarketing\Services;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;

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

        return $model->getMedia()
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

        if (is_string($value) && ! ctype_digit($value)) {
            return $value;
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
}

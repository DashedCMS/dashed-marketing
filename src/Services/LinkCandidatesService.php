<?php

namespace Dashed\DashedMarketing\Services;

class LinkCandidatesService
{
    /**
     * @return array<int, array{type: string, title: string, url: string}>
     */
    public function forLocale(string $locale, int $limit = 20): array
    {
        $pool = [];

        try {
            $routeModels = (array) cms()->builder('routeModels');
        } catch (\Throwable) {
            return [];
        }

        foreach ($routeModels as $entry) {
            $class = is_array($entry) ? ($entry['class'] ?? null) : (is_string($entry) ? $entry : null);
            if (! $class || ! class_exists($class)) {
                continue;
            }

            try {
                $entities = $class::query()->limit($limit)->get();
            } catch (\Throwable) {
                continue;
            }

            foreach ($entities as $entity) {
                $name = $entity->name ?? $entity->title ?? null;
                if (is_array($name)) {
                    $name = $name[$locale] ?? reset($name) ?? '';
                }

                $slug = $entity->slug ?? '';
                if (is_array($slug)) {
                    $slug = $slug[$locale] ?? reset($slug) ?? '';
                }

                $url = method_exists($entity, 'getUrl') ? $entity->getUrl() : '/'.ltrim((string) $slug, '/');

                $pool[] = [
                    'type' => class_basename($class),
                    'title' => (string) $name,
                    'url' => (string) $url,
                ];

                if (count($pool) >= $limit) {
                    return $pool;
                }
            }
        }

        return $pool;
    }
}

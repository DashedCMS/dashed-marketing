<?php

namespace Dashed\DashedMarketing\Services;

class LinkCandidatesService
{
    /**
     * Full pool across all route-models, using the publicShowable() scope
     * when available. Meant for AI-driven topic matching: wide net first,
     * filter later. Caps at $maxTotal to keep memory / prompts bounded.
     *
     * @return array<int, array{type: string, title: string, url: string, subject_type: ?string, subject_id: ?int}>
     */
    public function allForLocale(string $locale, int $maxTotal = 500): array
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
                $query = $class::query();
                if (method_exists($class, 'scopePublicShowable')) {
                    $query->publicShowable();
                }
                $entities = $query->limit(max(50, $maxTotal))->get();
            } catch (\Throwable) {
                continue;
            }

            foreach ($entities as $entity) {
                $name = $entity->name ?? $entity->title ?? null;
                if (is_array($name)) {
                    $name = $name[$locale] ?? reset($name) ?? '';
                }
                if (! $name) {
                    continue;
                }

                $slug = $entity->slug ?? '';
                if (is_array($slug)) {
                    $slug = $slug[$locale] ?? reset($slug) ?? '';
                }

                try {
                    $url = method_exists($entity, 'getUrl') ? $entity->getUrl() : '/'.ltrim((string) $slug, '/');
                } catch (\Throwable) {
                    $url = '/'.ltrim((string) $slug, '/');
                }
                if (! $url) {
                    continue;
                }

                $pool[] = [
                    'type' => class_basename($class),
                    'title' => (string) $name,
                    'url' => (string) $url,
                    'subject_type' => $class,
                    'subject_id' => (int) $entity->getKey(),
                ];

                if (count($pool) >= $maxTotal) {
                    return $pool;
                }
            }
        }

        return $pool;
    }

    /**
     * @return array<int, array{type: string, title: string, url: string, subject_type: ?string, subject_id: ?int}>
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
                    'subject_type' => $class,
                    'subject_id' => (int) $entity->getKey(),
                ];

                if (count($pool) >= $limit) {
                    return $pool;
                }
            }
        }

        return $pool;
    }
}

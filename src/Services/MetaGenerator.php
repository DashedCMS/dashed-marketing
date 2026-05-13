<?php

namespace Dashed\DashedMarketing\Services;

use Throwable;
use Dashed\DashedAi\Facades\Ai;
use Spatie\Translatable\HasTranslations;
use Illuminate\Support\Facades\Log;
use Dashed\DashedMarketing\Services\Prompts\SeoAuditPromptBuilder;

/**
 * Generates meta titles and meta descriptions for a record in multiple
 * locales by calling the AI provider with the shared meta prompt and
 * writing the suggestions straight onto the record's metadata relation.
 *
 * Designed to be called from a queued job: every locale is wrapped in
 * try/catch so a single failed AI call cannot abort the rest. The caller
 * receives a per-locale summary that can be logged or shown to the admin.
 */
final class MetaGenerator
{
    /**
     * Build per-locale meta suggestions and write them to the subject.
     *
     * @param  array<int, string>  $locales
     * @return array<string, array{written: int, skipped: int, error: ?string}>
     */
    public function generateForRecord(object $subject, array $locales, ?string $instruction, bool $overwrite): array
    {
        $results = [];

        if (! $this->subjectSupportsMeta($subject)) {
            Log::warning('MetaGenerator: subject does not support translatable metadata', [
                'subject' => $subject::class ?? null,
                'subject_id' => method_exists($subject, 'getKey') ? $subject->getKey() : null,
            ]);

            return $results;
        }

        foreach ($locales as $locale) {
            $results[$locale] = $this->generateForLocale($subject, (string) $locale, $instruction, $overwrite);
        }

        return $results;
    }

    /**
     * @return array{written: int, skipped: int, error: ?string}
     */
    protected function generateForLocale(object $subject, string $locale, ?string $instruction, bool $overwrite): array
    {
        $result = ['written' => 0, 'skipped' => 0, 'error' => null];

        try {
            $context = $this->buildContext($subject, $locale, $instruction);
        } catch (Throwable $e) {
            $result['error'] = 'context: '.$e->getMessage();
            Log::warning('MetaGenerator: context build failed', [
                'subject' => $subject::class ?? null,
                'subject_id' => method_exists($subject, 'getKey') ? $subject->getKey() : null,
                'locale' => $locale,
                'error' => $e->getMessage(),
            ]);

            return $result;
        }

        try {
            $response = Ai::json(SeoAuditPromptBuilder::meta($context)) ?? [];
        } catch (Throwable $e) {
            $result['error'] = 'ai: '.$e->getMessage();
            Log::warning('MetaGenerator: AI call failed', [
                'subject' => $subject::class ?? null,
                'subject_id' => method_exists($subject, 'getKey') ? $subject->getKey() : null,
                'locale' => $locale,
                'error' => $e->getMessage(),
            ]);

            return $result;
        }

        $suggestions = $response['suggestions'] ?? null;
        if (! is_array($suggestions) || $suggestions === []) {
            return $result;
        }

        try {
            $metadata = $subject->metadata()->firstOrNew([]);
        } catch (Throwable $e) {
            $result['error'] = 'metadata: '.$e->getMessage();

            return $result;
        }

        $touched = false;

        foreach ($suggestions as $suggestion) {
            if (! is_array($suggestion)) {
                continue;
            }

            $field = $suggestion['field'] ?? null;
            $value = $suggestion['suggested_value'] ?? null;

            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            $attr = match ($field) {
                'meta_title' => 'title',
                'meta_description' => 'description',
                default => null,
            };

            if ($attr === null) {
                continue;
            }

            $current = '';
            try {
                $current = (string) $metadata->getTranslation($attr, $locale);
            } catch (Throwable) {
                $current = '';
            }

            if (! $overwrite && trim($current) !== '') {
                $result['skipped']++;

                continue;
            }

            try {
                $metadata->setTranslation($attr, $locale, trim($value));
                $touched = true;
                $result['written']++;
            } catch (Throwable $e) {
                Log::warning('MetaGenerator: setTranslation failed', [
                    'subject' => $subject::class ?? null,
                    'subject_id' => method_exists($subject, 'getKey') ? $subject->getKey() : null,
                    'locale' => $locale,
                    'attribute' => $attr,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($touched) {
            try {
                $metadata->save();
            } catch (Throwable $e) {
                $result['error'] = 'save: '.$e->getMessage();
                Log::warning('MetaGenerator: metadata save failed', [
                    'subject' => $subject::class ?? null,
                    'subject_id' => method_exists($subject, 'getKey') ? $subject->getKey() : null,
                    'locale' => $locale,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildContext(object $subject, string $locale, ?string $instruction): array
    {
        $name = $this->readTranslatable($subject, 'name', $locale)
            ?? $this->readTranslatable($subject, 'title', $locale)
            ?? '';
        $slug = $this->readTranslatable($subject, 'slug', $locale) ?? '';

        $metaTitle = '';
        $metaDescription = '';

        try {
            $metadata = $subject->metadata;
            if ($metadata !== null) {
                try {
                    $metaTitle = (string) $metadata->getTranslation('title', $locale);
                } catch (Throwable) {
                    $metaTitle = '';
                }
                try {
                    $metaDescription = (string) $metadata->getTranslation('description', $locale);
                } catch (Throwable) {
                    $metaDescription = '';
                }
            }
        } catch (Throwable) {
            //
        }

        $brand = '';
        try {
            if (class_exists(SocialContextBuilder::class)) {
                $brand = (string) app(SocialContextBuilder::class)->build('seo');
            }
        } catch (Throwable) {
            //
        }

        return [
            'subject' => [
                'type' => class_basename($subject::class),
                'id' => method_exists($subject, 'getKey') ? $subject->getKey() : null,
                'name' => $name,
                'slug' => $slug,
            ],
            'locale' => $locale,
            'brand' => $brand,
            'user_instruction' => $instruction,
            'current_meta' => ['title' => $metaTitle, 'description' => $metaDescription],
        ];
    }

    protected function subjectSupportsMeta(object $subject): bool
    {
        if (! method_exists($subject, 'metadata')) {
            return false;
        }

        // The metadata model itself must use Spatie's HasTranslations trait,
        // otherwise setTranslation/getTranslation are not available.
        try {
            $metadata = $subject->metadata()->getRelated();
        } catch (Throwable) {
            return false;
        }

        if (! is_object($metadata)) {
            return false;
        }

        $traits = class_uses_recursive($metadata::class);

        return isset($traits[HasTranslations::class]);
    }

    private function readTranslatable(object $model, string $attr, string $locale): ?string
    {
        if (method_exists($model, 'getTranslation')) {
            try {
                $value = $model->getTranslation($attr, $locale);

                return is_string($value) ? $value : null;
            } catch (Throwable) {
                //
            }
        }

        $value = $model->{$attr} ?? null;
        if (is_array($value)) {
            return (string) ($value[$locale] ?? reset($value) ?? '');
        }

        return is_string($value) ? $value : null;
    }
}

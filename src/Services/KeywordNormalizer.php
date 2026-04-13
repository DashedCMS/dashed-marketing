<?php

namespace Dashed\DashedMarketing\Services;

class KeywordNormalizer
{
    /**
     * Default stopwords used when the Laravel config facade is unavailable
     * (e.g. bare unit tests without full app bootstrap).
     *
     * @var array<string, array<int, string>>
     */
    protected const DEFAULT_STOPWORDS = [
        'nl' => ['de', 'het', 'een', 'en', 'of', 'maar', 'van', 'voor', 'op', 'aan', 'met', 'bij', 'te', 'in', 'is', 'zijn', 'was', 'waren', 'dat', 'die', 'deze', 'dit', 'er', 'ook', 'dan', 'wel', 'als'],
        'en' => ['the', 'a', 'an', 'and', 'or', 'but', 'of', 'for', 'on', 'at', 'with', 'by', 'to', 'in', 'is', 'are', 'was', 'were', 'that', 'this', 'these', 'those', 'there', 'also', 'than', 'as'],
    ];

    public function normalize(string $text, string $locale = 'nl'): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = $this->stripDiacritics($text);
        $text = preg_replace('/[^a-z0-9\s]/', ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    protected function stripDiacritics(string $text): string
    {
        // Prefer intl Transliterator when available (clean, combining-mark aware).
        if (class_exists(\Transliterator::class)) {
            $transliterator = \Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC;');
            if ($transliterator !== null) {
                $converted = $transliterator->transliterate($text);
                if (is_string($converted)) {
                    return $converted;
                }
            }
        }

        // Fallback: direct mapping for common Latin diacritics (covers NL + EN needs).
        $map = [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'ā' => 'a',
            'ç' => 'c', 'č' => 'c',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ē' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ī' => 'i',
            'ñ' => 'n',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o', 'ō' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ū' => 'u',
            'ý' => 'y', 'ÿ' => 'y',
            'ß' => 'ss',
        ];

        return strtr($text, $map);
    }

    /** @return array<int, string> */
    public function tokens(string $text, string $locale = 'nl'): array
    {
        $normalized = $this->normalize($text, $locale);
        $stopwords = $this->stopwordsFor($locale);

        $tokens = array_filter(
            explode(' ', $normalized),
            fn ($t) => $t !== '' && ! in_array($t, $stopwords, true),
        );

        return array_values($tokens);
    }

    /**
     * @param  array<int, string>  $a
     * @param  array<int, string>  $b
     */
    public function jaccard(array $a, array $b): float
    {
        $a = array_unique($a);
        $b = array_unique($b);

        if (empty($a) && empty($b)) {
            return 0.0;
        }

        $intersect = count(array_intersect($a, $b));
        $union = count(array_unique(array_merge($a, $b)));

        return $union === 0 ? 0.0 : $intersect / $union;
    }

    public function substringContains(string $haystack, string $needle, string $locale = 'nl'): bool
    {
        return str_contains(
            $this->normalize($haystack, $locale),
            $this->normalize($needle, $locale),
        );
    }

    /** @return array<int, string> */
    protected function stopwordsFor(string $locale): array
    {
        try {
            $configured = config("dashed-marketing-content.stopwords.{$locale}");
            if (is_array($configured) && ! empty($configured)) {
                return $configured;
            }
        } catch (\Throwable) {
            // Fall through to defaults when Laravel is not bootstrapped.
        }

        return self::DEFAULT_STOPWORDS[$locale] ?? [];
    }
}

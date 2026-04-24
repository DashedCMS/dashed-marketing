<?php

namespace Dashed\DashedMarketing\Support;

class FaqHeadingDetector
{
    /**
     * Detect whether a heading text refers to a FAQ section. FAQ content is
     * surfaced via the separate FAQ suggestions and gets its own block, so an
     * outline-heading that matches one of these patterns must be skipped to
     * avoid duplicating the FAQ as a content-block.
     */
    public static function isFaq(string $text): bool
    {
        $normalized = mb_strtolower(trim($text));
        if ($normalized === '') {
            return false;
        }

        $needles = [
            'veelgestelde vragen',
            'veel gestelde vragen',
            'faq',
            'faqs',
            'frequently asked questions',
            'häufig gestellte fragen',
            'haufig gestellte fragen',
            'questions fréquentes',
            'preguntas frecuentes',
        ];

        foreach ($needles as $needle) {
            if (str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
    }
}

<?php

namespace Dashed\DashedMarketing\Services;

class ArticleSanitizer
{
    /** @var array<int, string> */
    protected array $cliches;

    protected bool $replaceEmDashes;

    public function __construct(?array $config = null)
    {
        $config ??= $this->loadConfig();

        $this->cliches = $config['cliches'] ?? [];
        $this->replaceEmDashes = $config['replace_em_dashes'] ?? true;
    }

    public function sanitize(string $text): string
    {
        if ($this->replaceEmDashes) {
            $text = preg_replace('/\s*[-–]\s*/u', ', ', $text);
        }

        $text = strtr($text, [
            "\u{2018}" => "'",
            "\u{2019}" => "'",
            "\u{201C}" => '"',
            "\u{201D}" => '"',
        ]);

        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    /** @return array<int, string> */
    public function detectCliches(string $text): array
    {
        $haystack = mb_strtolower($text);
        $found = [];
        foreach ($this->cliches as $cliche) {
            if (mb_strpos($haystack, mb_strtolower($cliche)) !== false) {
                $found[] = $cliche;
            }
        }

        return $found;
    }

    /** @param array<int, array{heading: ?string, body: string}> $sections */
    public function sanitizeSections(array $sections): array
    {
        return array_map(function ($section) {
            $section['body'] = $this->sanitize($section['body'] ?? '');
            if (isset($section['heading'])) {
                $section['heading'] = $this->sanitize($section['heading']);
            }

            return $section;
        }, $sections);
    }

    /** @return array<string, mixed> */
    protected function loadConfig(): array
    {
        try {
            $loaded = config('dashed-marketing-content.sanitizer');
            if (is_array($loaded) && ! empty($loaded)) {
                return $loaded;
            }
        } catch (\Throwable) {
            // Fall through to defaults below when Laravel is not bootstrapped.
        }

        return [
            'replace_em_dashes' => true,
            'cliches' => [
                'in de snel veranderende wereld van',
                'het is belangrijk om te onthouden',
                'het is belangrijk om te vermelden',
                'duik in',
                'ontdek de geheimen van',
                'in deze digitale tijd',
                'laten we eens kijken naar',
                'welnu',
                'in dit artikel gaan we',
                'of het nu gaat om',
                'uiteindelijk is het aan jou',
                'de mogelijkheden zijn eindeloos',
                'revolutionair',
                'baanbrekend',
                'next-level',
                'game-changer',
                'in een notendop',
                'last but not least',
                'kortom',
                'al met al',
                'kort samengevat',
                'de waarheid is',
                'of je nu … of …',
                'aan het einde van de dag',
                'in een wereld waar',
                'de realiteit is dat',
                'één ding is zeker',
                'het gaat niet alleen om',
                'meer dan ooit tevoren',
                'moet je gewoon',
            ],
        ];
    }
}

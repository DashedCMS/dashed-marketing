<?php

namespace Dashed\DashedMarketing\Services;

use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedPages\Models\Page;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SocialContextSourceCollector
{
    public const MAX_OUTPUT_CHARACTERS = 30000;

    public function collect(int|string $siteId): string
    {
        $sections = [];

        $siteName = Customsetting::get('site_name', (string) $siteId);
        if (is_string($siteName) && $siteName !== '') {
            $sections[] = "## Site naam\n{$siteName}";
        }

        $homepageText = $this->fetchHomepageText();
        if (is_string($homepageText) && $homepageText !== '') {
            $sections[] = "## Homepage\n{$homepageText}";
        }

        $pagesSection = $this->collectTopPages($siteId);
        if ($pagesSection !== '') {
            $sections[] = "## Pagina's\n{$pagesSection}";
        }

        $output = implode("\n\n", $sections);

        if (mb_strlen($output) > self::MAX_OUTPUT_CHARACTERS) {
            $output = mb_substr($output, 0, self::MAX_OUTPUT_CHARACTERS);
        }

        return $output;
    }

    protected function fetchHomepageText(): ?string
    {
        $url = config('app.url');
        if (! is_string($url) || $url === '') {
            return null;
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders(['User-Agent' => 'DashedMarketingBot/1.0'])
                ->get($url);
        } catch (Throwable $e) {
            Log::warning('SocialContextSourceCollector: homepage fetch threw exception', [
                'url' => $url,
                'exception' => $e->getMessage(),
            ]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('SocialContextSourceCollector: homepage fetch returned non-success status', [
                'url' => $url,
                'status' => $response->status(),
            ]);

            return null;
        }

        $text = $this->htmlToText((string) $response->body());

        return $text !== '' ? $text : null;
    }

    protected function collectTopPages(int|string $siteId): string
    {
        $pages = Page::query()
            ->whereJsonContains('site_ids', (string) $siteId)
            ->where('public', 1)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        if ($pages->isEmpty()) {
            return '';
        }

        $locale = app()->getLocale();
        $fallbackLocale = config('app.fallback_locale', 'en');

        $blocks = [];
        foreach ($pages as $page) {
            $title = $this->extractPageTitle($page, $locale, $fallbackLocale);
            $body = $this->extractPageText($page);

            $blocks[] = "### {$title}\n{$body}";
        }

        return implode("\n\n", $blocks);
    }

    protected function extractPageTitle(Page $page, string $locale, string $fallbackLocale): string
    {
        $title = $page->getTranslation('name', $locale, false);
        if (! is_string($title) || $title === '') {
            $title = $page->getTranslation('name', $fallbackLocale, false);
        }

        if (! is_string($title) || $title === '') {
            return '(geen titel)';
        }

        return $title;
    }

    protected function extractPageText(Page $page): string
    {
        $content = null;

        try {
            $translations = $page->getTranslations('content');
        } catch (Throwable $e) {
            $translations = [];
        }

        if (is_array($translations) && $translations !== []) {
            // Prefer current locale, otherwise any non-empty translation.
            $locale = app()->getLocale();
            if (isset($translations[$locale]) && ! empty($translations[$locale])) {
                $content = $translations[$locale];
            } else {
                foreach ($translations as $value) {
                    if (! empty($value)) {
                        $content = $value;

                        break;
                    }
                }
            }
        }

        if ($content === null) {
            $content = $page->content;
        }

        if ($content === null) {
            return '';
        }

        if (is_string($content)) {
            $decoded = json_decode($content, true);
            if (is_array($decoded)) {
                $content = $decoded;
            } else {
                return $this->htmlToText($content);
            }
        }

        if (! is_array($content)) {
            return '';
        }

        $fragments = [];
        array_walk_recursive($content, function ($value) use (&$fragments) {
            if (is_string($value) && trim($value) !== '') {
                $fragments[] = $value;
            }
        });

        if ($fragments === []) {
            return '';
        }

        return $this->htmlToText(implode(' ', $fragments));
    }

    protected function htmlToText(string $html): string
    {
        // Strip <script> and <style> blocks entirely (incl. their contents).
        $stripped = preg_replace('#<script\b[^>]*>.*?</script>#is', ' ', $html) ?? $html;
        $stripped = preg_replace('#<style\b[^>]*>.*?</style>#is', ' ', $stripped) ?? $stripped;

        $text = strip_tags($stripped);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return trim($text);
    }
}

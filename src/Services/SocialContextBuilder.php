<?php

namespace Dashed\DashedMarketing\Services;

use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedMarketing\Models\Keyword;
use Dashed\DashedMarketing\Models\SocialCampaign;
use Dashed\DashedMarketing\Models\SocialHoliday;
use Dashed\DashedMarketing\Models\SocialPillar;
use Illuminate\Database\Eloquent\Model;

class SocialContextBuilder
{
    /**
     * @param  string|array<int, string>|null  $typeOrPlatform  legacy: a platform key. New: a type key (post/reel/story).
     * @param  array<int, string>|Model|null  $channelsOrSubject  new: the channel keys. Legacy: the subject model.
     */
    public function build($typeOrPlatform = null, $channelsOrSubject = null, ?Model $subject = null): string
    {
        // Backwards-compatible signature: build($platform, $subject) is still accepted.
        $type = null;
        $channels = [];
        if (is_array($channelsOrSubject)) {
            $type = is_string($typeOrPlatform) ? $typeOrPlatform : null;
            $channels = $channelsOrSubject;
        } elseif ($channelsOrSubject instanceof Model) {
            $subject = $channelsOrSubject;
        }

        $sections = [];

        $this->addBrandInfo($sections);
        $this->addPlatforms($sections);
        $this->addPillars($sections);
        $this->addCampaigns($sections);
        $this->addKeywords($sections, $subject);
        $this->addHolidays($sections);
        $this->addVisitableModels($sections, $subject);

        if ($type) {
            $this->addTypeRules($sections, $type);
        }
        if ($channels) {
            $this->addChannelRules($sections, $channels);
        }
        if (! $type && ! $channels && is_string($typeOrPlatform)) {
            // Legacy single-platform path
            $this->addPlatformRules($sections, $typeOrPlatform);
        }

        $this->addStyleGuide($sections);

        return implode("\n\n", array_filter($sections));
    }

    private function addTypeRules(array &$sections, string $type): void
    {
        $rules = config("dashed-marketing.types.{$type}");
        if (! $rules) {
            return;
        }

        $parts = [
            "Type: {$rules['label']}",
            "Format: {$rules['description']}",
            'Beeldverhouding(en): '.implode(' of ', $rules['ratios']),
            "Tips: {$rules['tips']}",
        ];

        $sections[] = "## Type post\n".implode("\n", $parts);
    }

    /**
     * @param  array<int, string>  $channels
     */
    private function addChannelRules(array &$sections, array $channels): void
    {
        $blocks = [];
        foreach ($channels as $key) {
            $rules = config("dashed-marketing.channels.{$key}");
            if (! $rules) {
                continue;
            }

            $blocks[] = "### {$rules['label']}\n"
                ."- Caption lengte: {$rules['caption_min']}-{$rules['caption_max']} tekens\n"
                ."- Hashtags: {$rules['hashtags_min']}-{$rules['hashtags_max']}\n"
                ."- Tips: {$rules['tips']}";
        }

        if ($blocks) {
            $sections[] = "## Kanalen waarop deze post komt\n".implode("\n\n", $blocks)
                ."\n\nSchrijf één caption die op alle bovenstaande kanalen werkt. Houd je aan de strengste limieten (kortste max, hoogste min).";
        }
    }

    private function addStyleGuide(array &$sections): void
    {
        $lines = [
            'Schrijf alsof een ervaren Nederlandse marketeer het typt, niet een AI.',
            '',
            'NIET gebruiken:',
            '- Em-dashes (—). Gebruik een komma, punt, of haakjes.',
            '- En-dashes (–) als koppelteken. Gebruik een gewoon streepje (-).',
            '- "Niet alleen X, maar ook Y" constructies.',
            '- Openers als "In de wereld van...", "Stel je voor...", "Ontdek...", "Wist je dat...".',
            '- Zinnen die beginnen met "Bovendien", "Daarnaast", "Verder", "Kortom", "Al met al".',
            '- Hol jargon: "naadloos", "krachtig", "ongekend", "ultieme", "revolutionair", "next level", "unlock", "ontketenen", "benutten", "tillen naar een hoger niveau", "game-changer", "boost".',
            '- Drie-in-een-rijtjes met parallelle structuur ("sneller, slimmer, sterker").',
            '- Rhetorische vragen als opener.',
            '- Uitroeptekens tenzij de context het echt vraagt (max 1 per post).',
            '- Emoji-spam. Max 1-2 emoji per post, alleen als ze iets toevoegen.',
            '- Geforceerd enthousiasme of "salesy" toon.',
            '- Holle afsluiters als "Klaar om te beginnen?", "Wat wacht je nog op?".',
            '- Woorden die AI verraden: "dive in", "delve", "explore", "embark", "navigate the landscape", "in het huidige digitale landschap".',
            '',
            'WEL doen:',
            '- Kort, concreet, direct. Spreektaal boven schrijftaal.',
            '- Gewone leestekens: komma, punt, dubbele punt, haakjes.',
            '- Laat één idee per zin landen. Varieer zinslengte.',
            '- Specifieke details (getallen, namen, plekken) boven vage claims.',
            '- Actieve vorm. Schrijf "wij doen X", niet "X wordt gedaan".',
            '- Als een zin met een AI-woord uit bovenstaande lijst begint, herschrijf hem.',
        ];

        $sections[] = "## Stijlregels (menselijke toon)\n".implode("\n", $lines);
    }

    private function addBrandInfo(array &$sections): void
    {
        $parts = [];

        $siteName = Customsetting::get('site_name');
        if ($siteName) {
            $parts[] = "Merk: {$siteName}";
        }

        $brandStory = Customsetting::get('ai_brand_story');
        if ($brandStory) {
            $parts[] = "Merkverhaal: {$brandStory}";
        }

        $writingStyle = Customsetting::get('ai_writing_style');
        if ($writingStyle) {
            $parts[] = "Schrijfstijl: {$writingStyle}";
        }

        $targetAudience = Customsetting::get('social_target_audience');
        if ($targetAudience) {
            $parts[] = "Doelgroep: {$targetAudience}";
        }

        $usps = Customsetting::get('social_usps');
        if ($usps) {
            $parts[] = "USPs: {$usps}";
        }

        if ($parts) {
            $sections[] = "## Merk\n".implode("\n", $parts);
        }
    }

    private function addPlatforms(array &$sections): void
    {
        $platforms = Customsetting::get('social_platforms');
        if ($platforms) {
            $platformList = is_array($platforms) ? $platforms : json_decode($platforms, true);
            if ($platformList) {
                $labels = array_map(
                    fn ($p) => config("dashed-marketing.platforms.{$p}.label", $p),
                    $platformList
                );
                $sections[] = "## Actieve platforms\n".implode(', ', $labels);
            }
        }
    }

    private function addPillars(array &$sections): void
    {
        $pillars = SocialPillar::all();
        if ($pillars->isEmpty()) {
            return;
        }

        $lines = $pillars->map(fn ($p) => "- {$p->name} ({$p->target_percentage}%): {$p->description}");
        $sections[] = "## Content pijlers\n".$lines->implode("\n");
    }

    private function addCampaigns(array &$sections): void
    {
        $campaigns = SocialCampaign::active()->get();
        if ($campaigns->isEmpty()) {
            return;
        }

        $lines = $campaigns->map(fn ($c) => "- {$c->name} ({$c->start_date->format('d/m')} t/m {$c->end_date->format('d/m')}): {$c->focus}");
        $sections[] = "## Actieve campagnes\n".$lines->implode("\n");
    }

    private function addKeywords(array &$sections, ?Model $subject): void
    {
        $keywords = Keyword::approved()->pluck('keyword');
        if ($keywords->isEmpty()) {
            return;
        }

        $sections[] = "## Goedgekeurde keywords\n".$keywords->implode(', ');
    }

    private function addHolidays(array &$sections): void
    {
        $holidays = SocialHoliday::upcoming(30)->get();
        if ($holidays->isEmpty()) {
            return;
        }

        $lines = $holidays->map(fn ($h) => "- {$h->date->format('d/m')}: {$h->name}");
        $sections[] = "## Komende feestdagen\n".$lines->implode("\n");
    }

    private function addVisitableModels(array &$sections, ?Model $subject): void
    {
        $routeModels = cms()->builder('routeModels');
        $classToKey = [];
        foreach ($routeModels as $key => $modelConfig) {
            if (! empty($modelConfig['class'])) {
                $classToKey[$modelConfig['class']] = $key;
            }
        }

        if ($subject) {
            $refKey = $classToKey[get_class($subject)] ?? null;
            $sections[] = "## Onderwerp\n".$this->serializeModel($subject, $refKey);

            return;
        }

        $maxProducts = config('dashed-marketing.context_builder.max_products', 50);
        $items = [];

        foreach ($routeModels as $key => $modelConfig) {
            $class = $modelConfig['class'] ?? null;
            if (! $class || ! class_exists($class)) {
                continue;
            }

            $query = $class::query()->limit($maxProducts);
            $models = $query->get();

            foreach ($models as $model) {
                $items[] = $this->serializeModel($model, $key);
                if (count($items) >= $maxProducts) {
                    break 2;
                }
            }
        }

        if ($items) {
            $intro = "Gebruik de [ref:type:id] tags om te verwijzen naar een specifiek item. "
                ."Beschikbare types: ".implode(', ', array_keys($classToKey)).".";

            $sections[] = "## Beschikbare content\n".$intro."\n".implode("\n", $items);
        }
    }

    private function addPlatformRules(array &$sections, string $platform): void
    {
        $rules = config("dashed-marketing.platforms.{$platform}");
        if (! $rules) {
            return;
        }

        $parts = [
            "Platform: {$rules['label']}",
            "Caption lengte: {$rules['caption_min']}-{$rules['caption_max']} tekens",
            "Hashtags: {$rules['hashtags_min']}-{$rules['hashtags_max']}",
            'Beeldverhouding: '.implode(' of ', $rules['ratios']),
            "Tips: {$rules['tips']}",
        ];

        $sections[] = "## Platform regels\n".implode("\n", $parts);
    }

    private function serializeModel(Model $model, ?string $refKey = null): string
    {
        $attributes = $model->attributesToArray();

        $filtered = array_filter($attributes, function ($value, $key) {
            if ($value === null || $value === '') {
                return false;
            }
            if (in_array($key, ['id', 'created_at', 'updated_at', 'deleted_at', 'site_id'])) {
                return false;
            }

            return true;
        }, ARRAY_FILTER_USE_BOTH);

        $rawName = $filtered['name'] ?? $filtered['title'] ?? class_basename($model)." #{$model->id}";
        $name = is_array($rawName) ? (reset($rawName) ?: class_basename($model)." #{$model->id}") : $rawName;

        $ref = $refKey ? "[ref:{$refKey}:{$model->getKey()}] " : '';

        return "- {$ref}{$name}: ".json_encode($filtered, JSON_UNESCAPED_UNICODE);
    }
}

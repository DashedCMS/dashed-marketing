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
    public function build(?string $platform = null, ?Model $subject = null): string
    {
        $sections = [];

        $this->addBrandInfo($sections);
        $this->addPlatforms($sections);
        $this->addPillars($sections);
        $this->addCampaigns($sections);
        $this->addKeywords($sections, $subject);
        $this->addHolidays($sections);
        $this->addVisitableModels($sections, $subject);

        if ($platform) {
            $this->addPlatformRules($sections, $platform);
        }

        return implode("\n\n", array_filter($sections));
    }

    private function addBrandInfo(array &$sections): void
    {
        $parts = [];

        $siteName = Customsetting::get('site_name');
        if ($siteName) {
            $parts[] = "Merk: {$siteName}";
        }

        $description = Customsetting::get('claude_brand_description');
        if ($description) {
            $parts[] = "Merkbeschrijving: {$description}";
        }

        $toneOfVoice = Customsetting::get('claude_tone_voice');
        if ($toneOfVoice) {
            $parts[] = "Toon en schrijfstijl: {$toneOfVoice}";
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
        if ($subject) {
            $sections[] = "## Onderwerp\n".$this->serializeModel($subject);

            return;
        }

        $maxProducts = config('dashed-marketing.context_builder.max_products', 50);
        $routeModels = cms()->builder('routeModels');
        $items = [];

        foreach ($routeModels as $modelConfig) {
            $class = $modelConfig['class'] ?? null;
            if (! $class || ! class_exists($class)) {
                continue;
            }

            $query = $class::query()->limit($maxProducts);
            $models = $query->get();

            foreach ($models as $model) {
                $items[] = $this->serializeModel($model);
                if (count($items) >= $maxProducts) {
                    break 2;
                }
            }
        }

        if ($items) {
            $sections[] = "## Beschikbare content\n".implode("\n", $items);
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

    private function serializeModel(Model $model): string
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

        return "- {$name}: ".json_encode($filtered, JSON_UNESCAPED_UNICODE);
    }
}

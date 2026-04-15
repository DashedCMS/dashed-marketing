<?php

namespace Dashed\DashedMarketing\Jobs;

use Dashed\DashedAi\Facades\Ai;
use Dashed\DashedMarketing\Models\SocialPost;
use Dashed\DashedMarketing\Services\SocialContextBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateSocialPostJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<int, string>  $channels  channel keys from config('dashed-marketing.channels')
     */
    public function __construct(
        public string $type,
        public array $channels,
        public ?Model $subject,
        public ?int $pillarId,
        public ?int $campaignId,
        public ?string $toneOverride,
        public ?string $extraInstructions,
        public bool $includeKeywords,
        public ?string $scheduledAt,
        public string $siteId,
    ) {}

    public function handle(): void
    {
        $contextBuilder = new SocialContextBuilder;
        $context = $contextBuilder->build($this->type, $this->channels, $this->subject);

        $prompt = $this->buildPrompt($context);
        $result = Ai::json($prompt);

        if (! $result || ! isset($result['captions'])) {
            return;
        }

        $captions = array_values($result['captions']);
        $imagePrompts = array_values($result['image_prompts'] ?? []);

        if (empty($captions)) {
            return;
        }

        SocialPost::withoutGlobalScopes()->create([
            'site_id' => $this->siteId,
            'type' => $this->type,
            'channels' => $this->channels,
            'platform' => $this->channels[0] ?? null,
            'status' => 'concept',
            'caption' => $captions[0],
            'alternative_captions' => $captions,
            'hashtags' => $result['hashtags'] ?? null,
            'alt_text' => $result['alt_text'] ?? null,
            'image_prompt' => $imagePrompts[0] ?? null,
            'alternative_image_prompts' => $imagePrompts,
            'pillar_id' => $this->pillarId,
            'subject_type' => $this->subject ? get_class($this->subject) : null,
            'subject_id' => $this->subject?->id,
            'campaign_id' => $this->campaignId,
            'scheduled_at' => $this->scheduledAt,
        ]);
    }

    private function buildPrompt(string $context): string
    {
        $toneSection = $this->toneOverride ? "\nToon override: {$this->toneOverride}" : '';
        $extraSection = $this->extraInstructions ? "\nExtra instructies: {$this->extraInstructions}" : '';
        $keywordSection = $this->includeKeywords ? "\nVerwerk goedgekeurde keywords op natuurlijke wijze in captions en hashtags - nooit keyword stuffing." : '';

        return <<<PROMPT
        {$context}
        {$toneSection}{$extraSection}{$keywordSection}

        ## Jouw rol
        Je bent een senior social media marketeer met jaren ervaring in het schrijven van high-performing posts voor merken. Je weet precies wat de scroll stopt, wat waarde levert, en wat aanzet tot actie. Je denkt altijd vanuit de doelgroep, niet vanuit het merk. Je schrijft menselijk, scherp en nooit corporate of AI-achtig.

        ## Opdracht
        Schrijf 3 sterk verschillende caption varianten voor een social media post over het onderwerp hierboven. Dit is GEEN nieuwsbericht of productbeschrijving - dit is een social post die moet werken op het aangegeven platform.

        Elke variant moet voldoen aan:

        1. **Hook (eerste regel)** - Stop de scroll direct. Gebruik nieuwsgierigheid, een herkenbaar probleem, een verrassende claim, een sterke observatie, of een pijnpunt. Vermijd generieke openers ("Wist je dat…", "In deze post…", "Vandaag willen we…"). Eerste regel = alles.
        2. **Waarde of emotie** - Lever iets: inzicht, herkenning, een tip, een verhaal, of een mening. Schrijf alsof je 1-op-1 met één persoon uit de doelgroep praat.
        3. **Stem & toon** - Houd je strikt aan de merkbeschrijving, tone of voice en doelgroep hierboven. Als er geen merkstem is: schrijf warm, menselijk en to-the-point.
        4. **Structuur** - Korte zinnen. Witregels voor ritme en scanbaarheid. Emoji's alleen als ze waarde toevoegen, nooit als decoratie. Geen clichés, geen buzzwords, geen AI-taal ("in een wereld waar…", "ontdek hoe…", "in deze snel veranderende tijd…").
        5. **Call-to-action** - Sluit af met een heldere, passende CTA (reageren, opslaan, delen, klikken, DM'en, volgen). De CTA past bij het platform en het doel van de post.
        6. **Platformfit** - Respecteer de caption lengte, hashtag aantallen en tips uit de platform-regels hierboven. Schrijf niet alsof het voor een ander platform is. Voor Instagram/TikTok: visueler, persoonlijker. Voor LinkedIn: zakelijker maar nog steeds menselijk. Voor X: scherp en compact. Voor Facebook: verhalend.

        Varieer de 3 captions duidelijk qua invalshoek - bijvoorbeeld: (1) educatief/tip-based, (2) story-driven of persoonlijk, (3) conversational of mening/stelling. Herhaal elkaar niet.

        ## Hashtags
        Kies een mix van brede en niche hashtags die passen bij het onderwerp en de doelgroep. Respecteer het aantal uit de platform regels. Nooit irrelevante of spammy hashtags. Nederlandstalig waar dat past bij de doelgroep.

        ## Alt-tekst
        Eén korte, beschrijvende alt-tekst voor de bijbehorende afbeelding. Beschrijf wat er letterlijk te zien is (onderwerp, setting, sfeer), niet wat het symboliseert. Max 2 zinnen.

        ## Image prompts (Engels)
        Voor elke caption: één gedetailleerde image prompt in het Engels. Beschrijf subject, compositie, lighting, color palette, mood en stijl. Moet visueel passen bij het merk en de toon van die specifieke caption. Geen tekst in het beeld tenzij expliciet gevraagd.

        Retourneer UITSLUITEND geldig JSON, zonder uitleg, zonder markdown fences:
        {
            "captions": ["variant 1", "variant 2", "variant 3"],
            "hashtags": ["#tag1", "#tag2"],
            "alt_text": "beschrijvende alt-tekst voor de afbeelding",
            "image_prompts": [
                "English image prompt for variant 1",
                "English image prompt for variant 2",
                "English image prompt for variant 3"
            ]
        }
        PROMPT;
    }
}

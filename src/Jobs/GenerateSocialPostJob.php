<?php

namespace Dashed\DashedMarketing\Jobs;

use Illuminate\Bus\Queueable;
use Dashed\DashedAi\Facades\Ai;
use Illuminate\Queue\SerializesModels;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedMarketing\Models\SocialPost;
use Dashed\DashedMarketing\Services\SocialContextBuilder;

class GenerateSocialPostJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $platform,
        public ?Model $subject,
        public ?int $pillarId,
        public ?int $campaignId,
        public ?string $toneOverride,
        public ?string $extraInstructions,
        public bool $includeKeywords,
        public ?string $scheduledAt,
        public string $siteId,
    ) {
    }

    public function handle(): void
    {
        $contextBuilder = new SocialContextBuilder();
        $context = $contextBuilder->build($this->platform, $this->subject);

        $prompt = $this->buildPrompt($context);
        $result = Ai::json($prompt);

        if (! $result || ! isset($result['captions'])) {
            return;
        }

        foreach ($result['captions'] as $index => $caption) {
            SocialPost::withoutGlobalScopes()->create([
                'site_id' => $this->siteId,
                'platform' => $this->platform,
                'status' => 'concept',
                'caption' => $caption,
                'hashtags' => $result['hashtags'] ?? null,
                'alt_text' => $result['alt_text'] ?? null,
                'image_prompt' => $result['image_prompts'][$index] ?? null,
                'pillar_id' => $this->pillarId,
                'subject_type' => $this->subject ? get_class($this->subject) : null,
                'subject_id' => $this->subject?->id,
                'campaign_id' => $this->campaignId,
                'scheduled_at' => $this->scheduledAt,
            ]);
        }
    }

    private function buildPrompt(string $context): string
    {
        $toneSection = $this->toneOverride ? "\nToon override: {$this->toneOverride}" : '';
        $extraSection = $this->extraInstructions ? "\nExtra instructies: {$this->extraInstructions}" : '';
        $keywordSection = $this->includeKeywords ? "\nVerwerk goedgekeurde keywords in captions en hashtags waar relevant." : '';

        return <<<PROMPT
        {$context}
        {$toneSection}{$extraSection}{$keywordSection}

        Genereer content voor een social media post op dit platform.

        Retourneer UITSLUITEND geldig JSON:
        {
            "captions": ["variant 1", "variant 2", "variant 3"],
            "hashtags": ["#tag1", "#tag2"],
            "alt_text": "beschrijvende alt-tekst voor de afbeelding",
            "image_prompts": [
                "English image prompt describing composition, lighting, mood, color palette for variant 1",
                "English image prompt for variant 2",
                "English image prompt for variant 3"
            ]
        }
        PROMPT;
    }
}

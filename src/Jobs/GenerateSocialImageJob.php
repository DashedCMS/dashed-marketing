<?php

namespace Dashed\DashedMarketing\Jobs;

use Throwable;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Dashed\DashedCore\Models\Customsetting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedMarketing\Models\SocialPost;
use Dashed\DashedMarketing\Support\MarketingTask;
use Dashed\DashedMarketing\Services\SubjectImageResolver;
use Dashed\DashedMarketing\Filament\Resources\SocialPostResource;

class GenerateSocialImageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public SocialPost $post,
        public string $ratio = '4:5',
        public string $stylePreset = 'lifestyle',
        public ?string $referenceImageUrl = null,
        public float $referenceStrength = 0.7,
        public ?string $promptOverride = null,
        public ?string $taskKey = null,
        public ?int $userId = null,
    ) {
    }

    public const TASK_TYPE = 'social-images';

    public function handle(): void
    {
        // In het takenicoon van dashed-core: een onderdeel van de reeks die
        // de starter opende ($taskKey), anders een eigen taak van één stap.
        $key = MarketingTask::keyFor($this, $this->taskKey);
        MarketingTask::open(self::TASK_TYPE, __('Afbeelding genereren voor social post'), $key, 1, $this->userId, notify: true);

        $stored = $this->generate();

        MarketingTask::advance(
            self::TASK_TYPE,
            $key,
            $stored ? __('De afbeeldingen zijn aan de post toegevoegd.') : null,
            MarketingTask::url(SocialPostResource::class, 'edit', ['record' => $this->post]),
            __('Bekijk post'),
            $stored ? null : __('Er is geen afbeelding gegenereerd.'),
        );
    }

    public function failed(\Throwable $exception): void
    {
        MarketingTask::advance(self::TASK_TYPE, MarketingTask::keyFor($this, $this->taskKey), error: $exception);
    }

    /**
     * @return bool of er een afbeelding is opgeslagen
     */
    protected function generate(): bool
    {
        $apiKey = Customsetting::get('fal_api_key', $this->post->site_id);

        $prompt = $this->promptOverride ?: $this->post->image_prompt;

        if (! $apiKey || ! $prompt) {
            return false;
        }

        [$endpoint, $payload] = $this->referenceImageUrl
            ? $this->buildKontextRequest($prompt)
            : $this->buildFluxDevRequest($prompt);

        // De URL komt uit de staat van de post en kan sinds het klaarzetten
        // veranderd zijn: vlak voor de betaalde aanroep nog een keer toetsen.
        if ($this->referenceImageUrl && SubjectImageResolver::isProtectedUrl($this->referenceImageUrl)) {
            Log::warning('GenerateSocialImageJob: protected reference image refused', [
                'site_id' => $this->post->site_id,
                'social_post_id' => $this->post->id,
            ]);

            return false;
        }

        $charge = $this->charge($prompt);

        if ($charge === false) {
            return false;
        }

        try {
            $response = class_exists(\Dashed\DashedAi\Logging\FalRequest::class)
                ? \Dashed\DashedAi\Logging\FalRequest::post($endpoint, $payload, $apiKey, ['site_id' => $this->post->site_id, 'purpose' => 'social-post-image'], 120)
                : Http::withHeaders([
                    'Authorization' => "Key {$apiKey}",
                    'Content-Type' => 'application/json',
                ])->timeout(120)->post($endpoint, $payload);
        } catch (Throwable $exception) {
            $this->settle($charge, false);

            throw $exception;
        }

        $stored = false;

        if ($response->successful()) {
            $imageUrl = $response->json('images.0.url');
            if ($imageUrl) {
                $this->downloadAndStore($imageUrl);
                $stored = true;
            }
        }

        $this->settle($charge, $stored);

        return $stored;
    }

    /**
     * Legt de betaalde aanroep vast bij het dagplafond voor AI-beelden van de
     * site (dashed-files). Een beeld uit een referentie is een nieuw beeld,
     * dus het telt als genereren, net als in de mediabibliotheek. Zonder die
     * klasse (een oudere dashed-files) is er geen plafond en gaat het zoals
     * voorheen. Geeft false als het plafond bereikt is: dan gaat er niets de
     * deur uit en wordt er niets betaald, en de job stopt zonder fout, dus
     * ook zonder herkansing.
     */
    private function charge(string $prompt): mixed
    {
        if (! class_exists(\Dashed\DashedFiles\Services\AiSpend::class)) {
            return null;
        }

        try {
            return \Dashed\DashedFiles\Services\AiSpend::charge(
                \Dashed\DashedFiles\Services\AiSpend::KIND_GENERATE,
                $this->post->site_id !== null ? (string) $this->post->site_id : null,
                null,
                [
                    'params' => ['prompt' => mb_substr($prompt, 0, 2000), 'ratio' => $this->ratio],
                    'context' => ['purpose' => 'social-post-image', 'social_post_id' => $this->post->id],
                ],
            );
        } catch (\Dashed\DashedFiles\Services\AiCeilingReached $exception) {
            Log::warning('GenerateSocialImageJob: daily AI image ceiling reached', [
                'site_id' => $this->post->site_id,
                'social_post_id' => $this->post->id,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /** De uitkomst op de vastgelegde aanroep; het beeld zelf staat niet in de mediabibliotheek. */
    private function settle(mixed $charge, bool $succeeded): void
    {
        if (! $charge instanceof \Dashed\DashedFiles\Models\AiImageOperation) {
            return;
        }

        rescue(fn () => $charge->update($succeeded
            ? ['status' => \Dashed\DashedFiles\Models\AiImageOperation::STATUS_DONE]
            : ['status' => \Dashed\DashedFiles\Models\AiImageOperation::STATUS_FAILED, 'error' => 'failed']), null, false);
    }

    /**
     * Text-to-image: fal flux/dev.
     *
     * @return array{0: string, 1: array}
     */
    private function buildFluxDevRequest(string $prompt): array
    {
        return [
            'https://fal.run/fal-ai/flux/dev',
            [
                'prompt' => $prompt,
                'image_size' => $this->mapRatio($this->ratio),
                'num_images' => 1,
            ],
        ];
    }

    /**
     * Reference-image mode: fal flux-pro/kontext.
     * Kontext preserves the input image's composition, subject and layout
     * while applying the prompt - the right tool when the user wants the
     * reference to be copied faithfully.
     *
     * @return array{0: string, 1: array}
     */
    /**
     * Reference-image mode: fal nano-banana/edit (Google Gemini 2.5 Flash Image edit).
     * Exceptionally faithful to the input subject - keeps products pixel-accurate
     * while applying the prompt as an edit instruction.
     *
     * @return array{0: string, 1: array}
     */
    private function buildKontextRequest(string $userPrompt): array
    {
        $userPrompt = trim($userPrompt);

        $prompt = 'CRITICAL: The product / main subject in the input image MUST stay 100% pixel-identical. '
            .'Same exact shape, silhouette, proportions, colors, materials, textures, logos, labels, typography, '
            .'reflections and every fine detail. Do NOT redraw, restyle, recolor, reshape, retouch, upscale, '
            .'sharpen, smooth, denoise, or alter the product in any way whatsoever. Treat the product pixels '
            .'as immutable - copy them as-is into the new scene. '
            .'You may ONLY change the background, environment, surrounding scene, lighting on the surroundings '
            .'and props placed around (not on) the product, as described below. '
            .'If you are unsure whether a change touches the product, do not make that change. '
            ."\n\nScene: ".$userPrompt;

        return [
            'https://fal.run/fal-ai/nano-banana/edit',
            [
                'prompt' => $prompt,
                'image_urls' => [$this->referenceImageUrl],
                'num_images' => 1,
                'output_format' => 'png',
            ],
        ];
    }

    private function downloadAndStore(string $url): void
    {
        $contents = file_get_contents($url);
        // Disk-relative path (compatible with FileUpload->disk('public')) - no leading 'storage/'.
        $filename = 'social-generated/'.$this->post->id.'-'.time().'-'.uniqid().'.png';
        $path = storage_path('app/public/'.$filename);

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $contents);

        // Wrap the append in a row lock so concurrent jobs don't overwrite
        // each other's images array. Without this, two parallel workers each
        // read images=[a], append their own filename, and the last write wins,
        // losing one image.
        DB::transaction(function () use ($filename): void {
            $post = SocialPost::withoutGlobalScopes()
                ->whereKey($this->post->id)
                ->lockForUpdate()
                ->first();

            if (! $post) {
                return;
            }

            $existing = is_array($post->images) ? $post->images : [];
            $existing[] = $filename;

            $post->update([
                'images' => array_values(array_unique($existing)),
                // Keep legacy single image_path in sync with the first image so older
                // display code that still reads image_path keeps working.
                'image_path' => $post->image_path ?: $filename,
            ]);
        });
    }

    private function mapRatio(string $ratio): string
    {
        return match ($ratio) {
            '1:1' => 'square',
            '1:1_hd' => 'square_hd',
            '4:5', '2:3', '3:4' => 'portrait_4_3',
            '9:16' => 'portrait_16_9',
            '4:3' => 'landscape_4_3',
            '16:9' => 'landscape_16_9',
            default => 'square',
        };
    }
}

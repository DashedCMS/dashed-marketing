<?php

namespace Dashed\DashedMarketing\Jobs;

use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedMarketing\Models\SocialPost;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

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
    ) {}

    public function handle(): void
    {
        $apiKey = Customsetting::get('fal_api_key', $this->post->site_id);

        $prompt = $this->promptOverride ?: $this->post->image_prompt;

        if (! $apiKey || ! $prompt) {
            return;
        }

        [$endpoint, $payload] = $this->referenceImageUrl
            ? $this->buildKontextRequest($prompt)
            : $this->buildFluxDevRequest($prompt);

        $response = Http::withHeaders([
            'Authorization' => "Key {$apiKey}",
            'Content-Type' => 'application/json',
        ])->timeout(120)->post($endpoint, $payload);

        if ($response->successful()) {
            $imageUrl = $response->json('images.0.url');
            if ($imageUrl) {
                $this->downloadAndStore($imageUrl);
            }
        }
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

        $prompt = 'Keep the product in the input image 100% identical: same exact shape, silhouette, '
            .'proportions, colors, materials, textures, logos, labels and every fine detail. '
            .'Do not redraw, restyle, recolor or reshape the product in any way. '
            .'Only change the background, environment, lighting and surrounding scene as described. '
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

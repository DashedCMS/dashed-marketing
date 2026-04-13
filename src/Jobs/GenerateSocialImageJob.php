<?php

namespace Dashed\DashedMarketing\Jobs;

use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedMarketing\Models\SocialPost;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
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
    ) {}

    public function handle(): void
    {
        $apiKey = Customsetting::get('social_fal_api_key', $this->post->site_id);

        if (! $apiKey || ! $this->post->image_prompt) {
            return;
        }

        [$endpoint, $payload] = $this->referenceImageUrl
            ? $this->buildKontextRequest()
            : $this->buildFluxDevRequest();

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
    private function buildFluxDevRequest(): array
    {
        return [
            'https://fal.run/fal-ai/flux/dev',
            [
                'prompt' => $this->post->image_prompt,
                'image_size' => $this->mapRatio($this->ratio),
                'num_images' => 1,
            ],
        ];
    }

    /**
     * Reference-image mode: fal flux-pro/kontext.
     * Kontext preserves the input image's composition, subject and layout
     * while applying the prompt — the right tool when the user wants the
     * reference to be copied faithfully.
     *
     * @return array{0: string, 1: array}
     */
    private function buildKontextRequest(): array
    {
        $prompt = 'Preserve the subject, composition, pose, colors and framing from the reference image exactly. '
            .'Do not change the subject, background or layout. '
            .$this->post->image_prompt;

        return [
            'https://fal.run/fal-ai/flux-pro/kontext',
            [
                'prompt' => $prompt,
                'image_url' => $this->referenceImageUrl,
                'aspect_ratio' => $this->mapKontextAspectRatio($this->ratio),
                'num_images' => 1,
                'safety_tolerance' => '2',
                'output_format' => 'png',
            ],
        ];
    }

    private function mapKontextAspectRatio(string $ratio): string
    {
        return match ($ratio) {
            '1:1' => '1:1',
            '4:5' => '4:5',
            '9:16' => '9:16',
            '2:3' => '2:3',
            '3:4' => '3:4',
            '4:3' => '4:3',
            '16:9' => '16:9',
            '3:2' => '3:2',
            default => '1:1',
        };
    }

    private function downloadAndStore(string $url): void
    {
        $contents = file_get_contents($url);
        $filename = 'social-generated/'.$this->post->id.'-'.time().'.png';
        $path = storage_path('app/public/'.$filename);

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $contents);

        $this->post->update([
            'image_path' => 'storage/'.$filename,
        ]);
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

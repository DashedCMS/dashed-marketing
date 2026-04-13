<?php

namespace Dashed\DashedMarketing\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Dashed\DashedCore\Models\Customsetting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedMarketing\Models\SocialPost;

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
    ) {
    }

    public function handle(): void
    {
        $apiKey = Customsetting::get('social_fal_api_key', $this->post->site_id);

        if (! $apiKey || ! $this->post->image_prompt) {
            return;
        }

        $payload = [
            'prompt' => $this->post->image_prompt,
            'image_size' => $this->mapRatio($this->ratio),
            'num_images' => 1,
        ];

        if ($this->referenceImageUrl) {
            $payload['image_url'] = $this->referenceImageUrl;
            $payload['strength'] = $this->referenceStrength;
        }

        $response = Http::withHeaders([
            'Authorization' => "Key {$apiKey}",
            'Content-Type' => 'application/json',
        ])->post('https://fal.run/fal-ai/flux/dev', $payload);

        if ($response->successful()) {
            $imageUrl = $response->json('images.0.url');
            if ($imageUrl) {
                $this->downloadAndStore($imageUrl);
            }
        }
    }

    private function downloadAndStore(string $url): void
    {
        $contents = file_get_contents($url);
        $filename = 'social-generated/' . $this->post->id . '-' . time() . '.png';
        $path = storage_path('app/public/' . $filename);

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $contents);

        $this->post->update([
            'image_path' => 'storage/' . $filename,
        ]);
    }

    private function mapRatio(string $ratio): string
    {
        return match ($ratio) {
            '1:1' => 'square',
            '4:5' => 'portrait_4_5',
            '9:16' => 'portrait_9_16',
            '2:3' => 'portrait_2_3',
            '16:9' => 'landscape_16_9',
            default => 'square',
        };
    }
}

<?php

namespace Dashed\DashedMarketing\Commands;

use Dashed\DashedMarketing\Services\ProductPromptGenerator;
use Illuminate\Console\Command;

class GenerateProductPromptCommand extends Command
{
    protected $signature = 'dashed:generate-product-prompt
        {imagePath : Absolute or storage-relative path to the product photo}
        {theme : Theme / occasion (e.g. Koningsdag, Kerst, Lente)}
        {--model= : Override Claude model (default claude-sonnet-4-6)}
        {--brand-name= : Override brand name (default site_name customsetting)}
        {--brand-story= : Override brand story (default ai_brand_story customsetting)}
        {--writing-style= : Override writing style (default ai_writing_style customsetting)}
        {--product-name= : Explicit product name to anchor the prompt}
        {--product-context= : Multi-line product info (material, finish, dimensions, USP) to anchor the prompt}
        {--instructions= : Extra free-form art-direction}
        {--no-cache : Bypass the cache and call Claude}';

    protected $description = 'Generate a styled product image prompt from a product photo + theme via Claude vision.';

    public function handle(ProductPromptGenerator $generator): int
    {
        $rawPath = (string) $this->argument('imagePath');
        $theme = (string) $this->argument('theme');

        $absolute = $this->resolvePath($rawPath);
        if (! $absolute) {
            $this->error("Image not found: {$rawPath}");

            return self::FAILURE;
        }

        $options = array_filter([
            'model' => $this->option('model'),
            'brand_name' => $this->option('brand-name'),
            'brand_story' => $this->option('brand-story'),
            'writing_style' => $this->option('writing-style'),
            'product_name' => $this->option('product-name'),
            'product_context' => $this->option('product-context'),
            'extra_instructions' => $this->option('instructions'),
        ], fn ($v) => $v !== null && $v !== '');

        if ($this->option('no-cache')) {
            $options['cache_ttl'] = 0;
        }

        $themeKey = strtolower(preg_replace('/[^a-z0-9]+/i', '', $theme));
        $themeKnown = in_array($themeKey, ProductPromptGenerator::knownThemes(), true);

        $this->line('');
        $this->line('<comment>== Product prompt generator ==</comment>');
        $this->line("Image:  <info>{$absolute}</info>");
        $this->line("Theme:  <info>{$theme}</info>".($themeKnown ? ' <fg=green>(known iconography)</>' : ' <fg=yellow>(no preset, AI will improvise)</>'));
        $this->line('Model:  <info>'.($options['model'] ?? ProductPromptGenerator::DEFAULT_MODEL).'</info>');
        if (! empty($options['extra_instructions'])) {
            $this->line('Extra:  <info>'.$options['extra_instructions'].'</info>');
        }
        $this->line('');

        $started = microtime(true);

        try {
            $prompt = $generator->generate($absolute, $theme, $options);
        } catch (\Throwable $e) {
            $this->error('Generation failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $elapsed = number_format(microtime(true) - $started, 2);

        $this->line('<comment>--- Generated prompt ('.$elapsed.'s, '.strlen($prompt).' chars) ---</comment>');
        $this->line('');
        $this->line($prompt);
        $this->line('');
        $this->line('<comment>--- end ---</comment>');

        return self::SUCCESS;
    }

    private function resolvePath(string $raw): ?string
    {
        if (is_file($raw)) {
            return $raw;
        }

        $candidates = [
            base_path($raw),
            storage_path('app/public/'.$raw),
            storage_path('app/'.$raw),
            public_path($raw),
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}

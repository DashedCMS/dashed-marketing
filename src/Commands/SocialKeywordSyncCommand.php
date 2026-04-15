<?php

namespace Dashed\DashedMarketing\Commands;

use Dashed\DashedMarketing\Contracts\KeywordResearchAdapter;
use Illuminate\Console\Command;

class SocialKeywordSyncCommand extends Command
{
    protected $signature = 'social:keyword-sync';

    protected $description = 'Sync keywords via the configured keyword research adapter (noop when no external adapter is configured).';

    public function handle(): void
    {
        if (! app()->bound(KeywordResearchAdapter::class)) {
            $this->info('No keyword research adapter configured - skipping sync.');

            return;
        }

        $adapter = app(KeywordResearchAdapter::class);

        // External adapters (e.g. SEMrush, Ahrefs) can implement sync logic here.
        if (method_exists($adapter, 'sync')) {
            $adapter->sync();
            $this->info('Keyword sync completed via '.get_class($adapter).'.');
        } else {
            $this->warn('Adapter '.get_class($adapter).' does not implement sync().');
        }
    }
}

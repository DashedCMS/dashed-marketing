<?php

namespace Dashed\DashedMarketing\Commands;

use Illuminate\Console\Command;
use Dashed\DashedMarketing\Adapters\ClaudeKeywordAdapter;
use Dashed\DashedMarketing\Contracts\KeywordResearchAdapter;

class SocialKeywordSyncCommand extends Command
{
    protected $signature = 'social:keyword-sync';

    protected $description = 'Sync keywords via the configured keyword research adapter (noop for Claude adapter).';

    public function handle(): void
    {
        $adapter = app(KeywordResearchAdapter::class);

        // ClaudeKeywordAdapter is the default local adapter and does not support
        // external syncing — skip silently.
        if ($adapter instanceof ClaudeKeywordAdapter) {
            $this->info('Using Claude keyword adapter — no external sync required.');

            return;
        }

        // External adapters (e.g. SEMrush, Ahrefs) can implement sync logic here.
        if (method_exists($adapter, 'sync')) {
            $adapter->sync();
            $this->info('Keyword sync completed via ' . get_class($adapter) . '.');
        } else {
            $this->warn('Adapter ' . get_class($adapter) . ' does not implement sync().');
        }
    }
}

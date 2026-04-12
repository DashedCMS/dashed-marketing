<?php

namespace Dashed\DashedMarketing\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

// TODO: Fully implement — migrated from dashed-articles RunKeywordResearchJob.
// Update model references to use Dashed\DashedMarketing\Models\KeywordResearch and Keyword.
// Refactor to use KeywordResearchAdapter contract instead of direct Claude calls.
class RunKeywordResearchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $keywordResearchId,
    ) {}

    public function handle(): void
    {
        // TODO: Implement keyword research using KeywordResearch model and KeywordResearchAdapter.
    }
}

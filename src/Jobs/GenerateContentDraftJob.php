<?php

namespace Dashed\DashedMarketing\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

// TODO: Fully implement — migrated from dashed-articles GenerateArticleJob.
// Update model references to use Dashed\DashedMarketing\Models\ContentDraft.
class GenerateContentDraftJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $contentDraftId,
    ) {}

    public function handle(): void
    {
        // TODO: Implement content draft generation using ContentDraft model and ClaudeHelper.
    }
}

<?php

namespace Dashed\DashedMarketing\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

// TODO: Fully implement — migrated from dashed-articles GenerateArticleJob.
// Update model references to use Dashed\DashedMarketing\Models\ContentDraft.
class GenerateContentDraftJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $contentDraftId,
    ) {
    }

    public function handle(): void
    {
        // TODO: Implement content draft generation using ContentDraft model and Ai::text() / Ai::json().
    }
}

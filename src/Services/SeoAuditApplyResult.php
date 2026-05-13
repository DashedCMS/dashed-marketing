<?php

namespace Dashed\DashedMarketing\Services;

final class SeoAuditApplyResult
{
    /**
     * @param  array<string, string>  $failures
     */
    public function __construct(
        public int $applied = 0,
        public int $failed = 0,
        public int $skipped = 0,
        public array $failures = [],
    ) {}

    public function recordApplied(): void
    {
        $this->applied++;
    }

    public function recordSkipped(): void
    {
        $this->skipped++;
    }

    public function recordFailure(string $key, string $reason): void
    {
        $this->failed++;
        $this->failures[$key] = $reason;
    }

    public function summary(): string
    {
        return sprintf('%d toegepast, %d overgeslagen, %d mislukt', $this->applied, $this->skipped, $this->failed);
    }
}

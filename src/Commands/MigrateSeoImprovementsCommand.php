<?php

namespace Dashed\DashedMarketing\Commands;

use Dashed\DashedMarketing\Models\SeoAudit;
use Dashed\DashedMarketing\Models\SeoImprovement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MigrateSeoImprovementsCommand extends Command
{
    protected $signature = 'dashed-marketing:migrate-seo-improvements';

    protected $description = 'Convert legacy SeoImprovement records into the new SeoAudit model';

    public function handle(): int
    {
        $total = SeoImprovement::query()->count();
        $this->info("Converting {$total} SeoImprovement records to SeoAudit...");

        $allowed = ['name', 'slug', 'excerpt', 'meta_title', 'meta_description'];
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        SeoImprovement::query()->chunk(100, function ($imps) use ($allowed, $bar) {
            foreach ($imps as $imp) {
                $exists = SeoAudit::where('subject_type', $imp->subject_type)
                    ->where('subject_id', $imp->subject_id)
                    ->exists();
                if ($exists) {
                    $bar->advance();

                    continue;
                }

                DB::transaction(function () use ($imp, $allowed) {
                    $audit = SeoAudit::create([
                        'subject_type' => $imp->subject_type,
                        'subject_id' => $imp->subject_id,
                        'status' => 'archived',
                        'analysis_summary' => $imp->analysis_summary,
                        'applied_at' => $imp->applied_at,
                        'applied_by' => $imp->applied_by,
                        'created_by' => $imp->created_by,
                        'archived_at' => now(),
                    ]);

                    $statusMap = (array) ($imp->block_proposals_status ?? []);

                    foreach ((array) $imp->field_proposals as $k => $v) {
                        if (! in_array($k, $allowed, true)) {
                            Log::warning('MigrateSeoImprovements: skipping unknown field_proposal key', [
                                'improvement_id' => $imp->id,
                                'key' => $k,
                            ]);

                            continue;
                        }
                        if (! is_string($v)) {
                            continue;
                        }
                        $audit->metaSuggestions()->create([
                            'field' => $k,
                            'suggested_value' => $v,
                            'status' => in_array($statusMap[$k] ?? null, ['pending', 'applied', 'rejected', 'edited'], true)
                                ? $statusMap[$k]
                                : 'pending',
                            'priority' => 'medium',
                        ]);
                    }

                    foreach ((array) $imp->block_proposals as $key => $val) {
                        $audit->blockSuggestions()->create([
                            'block_index' => null,
                            'block_key' => (string) $key,
                            'block_type' => 'legacy',
                            'field_key' => '_legacy',
                            'is_new_block' => false,
                            'suggested_value' => is_string($val) ? $val : json_encode($val, JSON_UNESCAPED_UNICODE),
                            'status' => in_array($statusMap["block.{$key}"] ?? null, ['pending', 'applied', 'rejected', 'edited', 'failed'], true)
                                ? $statusMap["block.{$key}"]
                                : 'pending',
                            'priority' => 'medium',
                        ]);
                    }

                    DB::table('dashed__content_apply_logs')
                        ->where('seo_improvement_id', $imp->id)
                        ->whereNull('audit_id')
                        ->update(['audit_id' => $audit->id]);
                });

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info('Done.');

        return self::SUCCESS;
    }
}

<?php

namespace Dashed\DashedMarketing\Commands;

use Dashed\DashedMarketing\Models\SeoAudit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class MigrateSeoImprovementsCommand extends Command
{
    protected $signature = 'dashed-marketing:migrate-seo-improvements';

    protected $description = 'Convert legacy SeoImprovement records into the new SeoAudit model (reads from dashed__seo_improvements directly, the Eloquent model has been removed).';

    public function handle(): int
    {
        if (! Schema::hasTable('dashed__seo_improvements')) {
            $this->info('Legacy table dashed__seo_improvements not present. Nothing to migrate.');

            return self::SUCCESS;
        }

        $total = DB::table('dashed__seo_improvements')->count();
        $this->info("Converting {$total} SeoImprovement records to SeoAudit...");

        $allowed = ['name', 'slug', 'excerpt', 'meta_title', 'meta_description'];
        $metaStatuses = ['pending', 'applied', 'rejected', 'edited'];
        $blockStatuses = ['pending', 'applied', 'rejected', 'edited', 'failed'];
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        DB::table('dashed__seo_improvements')->orderBy('id')->chunk(100, function ($imps) use ($allowed, $metaStatuses, $blockStatuses, $bar) {
            foreach ($imps as $imp) {
                $exists = SeoAudit::where('subject_type', $imp->subject_type)
                    ->where('subject_id', $imp->subject_id)
                    ->exists();
                if ($exists) {
                    $bar->advance();

                    continue;
                }

                $fieldProposals = json_decode($imp->field_proposals ?? 'null', true) ?: [];
                $blockProposals = json_decode($imp->block_proposals ?? 'null', true) ?: [];
                $statusMap = json_decode($imp->block_proposals_status ?? 'null', true) ?: [];

                DB::transaction(function () use ($imp, $allowed, $metaStatuses, $blockStatuses, $fieldProposals, $blockProposals, $statusMap) {
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

                    foreach ((array) $fieldProposals as $k => $v) {
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
                            'status' => in_array($statusMap[$k] ?? null, $metaStatuses, true) ? $statusMap[$k] : 'pending',
                            'priority' => 'medium',
                        ]);
                    }

                    foreach ((array) $blockProposals as $key => $val) {
                        $audit->blockSuggestions()->create([
                            'block_index' => null,
                            'block_key' => (string) $key,
                            'block_type' => 'legacy',
                            'field_key' => '_legacy',
                            'is_new_block' => false,
                            'suggested_value' => is_string($val) ? $val : json_encode($val, JSON_UNESCAPED_UNICODE),
                            'status' => in_array($statusMap["block.{$key}"] ?? null, $blockStatuses, true) ? $statusMap["block.{$key}"] : 'pending',
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

<?php

namespace Dashed\DashedMarketing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Dashed\DashedPopups\Models\PopupFollowUpFlow;

/**
 * Ledger of form submitters who have been enrolled into a PopupFollowUpFlow.
 *
 * Each row tracks one (form, flow, email) tuple. The unique index on those
 * three columns prevents duplicate enrolments. `sent_steps` records which
 * flow-step IDs have been delivered, and `next_mail_at` is the scheduled
 * timestamp for the next step (read by the same scheduler that drives
 * PopupFollowUp delivery).
 */
class FormFlowEnrollment extends Model
{
    protected $table = 'dashed__form_flow_enrollments';

    protected $fillable = [
        'form_id',
        'flow_id',
        'email',
        'locale',
        'site_id',
        'sent_steps',
        'next_mail_at',
        'enrolled_at',
        'completed_at',
        'unenrolled_at',
    ];

    protected $casts = [
        'sent_steps' => 'array',
        'next_mail_at' => 'datetime',
        'enrolled_at' => 'datetime',
        'completed_at' => 'datetime',
        'unenrolled_at' => 'datetime',
    ];

    public function flow(): BelongsTo
    {
        return $this->belongsTo(PopupFollowUpFlow::class, 'flow_id');
    }

    public function markStepSent(int $flowStepId): void
    {
        $sent = is_array($this->sent_steps) ? $this->sent_steps : [];
        $key = (string) $flowStepId;
        if (isset($sent[$key])) {
            return;
        }

        $sent[$key] = now()->toIso8601String();
        $this->forceFill(['sent_steps' => $sent])->save();
        $this->recomputeNextMailAt();
    }

    public function recomputeNextMailAt(): void
    {
        $next = null;

        if (! $this->unenrolled_at && ! $this->completed_at) {
            try {
                $flow = $this->flow;
            } catch (\Throwable) {
                $flow = null;
            }

            if ($flow) {
                $sent = is_array($this->sent_steps) ? $this->sent_steps : [];
                $sentIds = array_map('strval', array_keys($sent));

                $startedAt = $this->enrolled_at ?: ($this->created_at ?? now());

                try {
                    $query = $flow->emails()->where('is_active', true);
                    if (! empty($sentIds)) {
                        $query->whereNotIn('id', $sentIds);
                    }
                    $nextStep = $query
                        ->orderBy('send_after_minutes')
                        ->orderBy('id')
                        ->first();

                    if ($nextStep) {
                        $next = $startedAt->copy()->addMinutes((int) ($nextStep->send_after_minutes ?? 0));
                    }
                } catch (\Throwable) {
                    // Flow-related tables may not exist yet on a fresh install;
                    // skipping next_mail_at calculation lets the scheduler pick
                    // up the row later once dashed-popups migrations have run.
                }
            }
        }

        $currentIso = $this->next_mail_at?->toIso8601String();
        $nextIso = $next?->toIso8601String();
        if ($currentIso !== $nextIso) {
            $this->forceFill(['next_mail_at' => $next])->save();
        }
    }
}

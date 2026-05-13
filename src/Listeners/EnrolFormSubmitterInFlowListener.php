<?php

namespace Dashed\DashedMarketing\Listeners;

use Dashed\DashedForms\Models\Form;
use Dashed\DashedForms\Events\FormSubmitted;
use Dashed\DashedMarketing\Models\FormFlowEnrollment;

/**
 * Synchronous v1 listener: on FormSubmitted, find the submitting form, look
 * up its enrollment_flow_id, and create (or re-engage) a FormFlowEnrollment
 * row for the submitter's email.
 *
 * Once Bundle 2 ships `Dashed\DashedCore\Jobs\Concerns\HandlesQueueFailures`,
 * this listener should be promoted to a queued job that uses the trait so
 * downstream alert/retry behavior is centralised. v1 stays synchronous -
 * the work is one find + one upsert and is fast enough to keep inline.
 */
class EnrolFormSubmitterInFlowListener
{
    public function handle(FormSubmitted $event): void
    {
        $email = is_string($event->email) ? trim($event->email) : '';
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $form = Form::find($event->form_id);
        if (! $form || ! $form->enrollment_flow_id) {
            return;
        }

        $flowId = (int) $form->enrollment_flow_id;

        $existing = FormFlowEnrollment::query()
            ->where('form_id', $form->id)
            ->where('flow_id', $flowId)
            ->where('email', $email)
            ->first();

        if ($existing) {
            if ($existing->unenrolled_at) {
                $existing->forceFill([
                    'unenrolled_at' => null,
                    'next_mail_at' => now(),
                    'locale' => $event->locale,
                    'site_id' => $event->site_id !== null ? (string) $event->site_id : $existing->site_id,
                ])->save();
                $existing->recomputeNextMailAt();
            }

            return;
        }

        $enrollment = FormFlowEnrollment::create([
            'form_id' => $form->id,
            'flow_id' => $flowId,
            'email' => $email,
            'locale' => $event->locale,
            'site_id' => $event->site_id !== null ? (string) $event->site_id : null,
            'sent_steps' => [],
            'enrolled_at' => now(),
            'next_mail_at' => now(),
        ]);

        $enrollment->recomputeNextMailAt();
    }
}

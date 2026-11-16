<?php

namespace Dashed\DashedMarketing\Support;

use Throwable;
use Illuminate\Support\Str;
use Dashed\DashedCore\Models\BackgroundTask;

/**
 * Meldt werk van dit pakket in het takenicoon van dashed-core ("Bezig" en
 * "Recent"). Een taak hangt aan een sleutel: het uuid van de job (gelijk bij
 * een nieuwe poging en in failed()), het id van een concept, of een sleutel
 * die de starter van een reeks jobs aan elke job meegeeft. Zo is een concept
 * dat uit meerdere jobs bestaat, of een bulkactie, één taak.
 *
 * Alles staat achter class_exists (een oudere dashed-core kent BackgroundTask
 * niet) en binnen rescue(): de administratie mag het werk nooit tegenhouden.
 */
class MarketingTask
{
    /** @var \WeakMap<object, string>|null */
    protected static ?\WeakMap $instanceKeys = null;

    public static function available(): bool
    {
        return class_exists(BackgroundTask::class);
    }

    /**
     * De sleutel van een job in de wachtrij. Null als de job buiten de
     * wachtrij om wordt aangeroepen.
     */
    public static function jobKey(object $job): ?string
    {
        return rescue(fn () => ($job->job ?? null)?->uuid(), null, report: false);
    }

    /**
     * De sleutel waaronder een job zijn onderdeel meldt: die van de reeks
     * waar hij bij hoort, anders het uuid van de job in de wachtrij, en
     * buiten de wachtrij om (een rechtstreekse handle()) een vaste sleutel
     * voor deze instantie, zodat open() en advance() dezelfde taak vinden.
     */
    public static function keyFor(object $job, ?string $taskKey = null): string
    {
        if ($taskKey !== null) {
            return $taskKey;
        }

        self::$instanceKeys ??= new \WeakMap();

        return self::jobKey($job) ?? (self::$instanceKeys[$job] ??= self::key());
    }

    public static function key(): string
    {
        return (string) Str::uuid();
    }

    /**
     * Start de taak met deze sleutel, ook als er nog een oude met dezelfde
     * sleutel staat: die begint dan opnieuw.
     */
    public static function start(string $type, string $label, ?string $key, ?int $total = null, ?int $userId = null, bool $notify = false): ?BackgroundTask
    {
        if (! self::available()) {
            return null;
        }

        return rescue(function () use ($type, $label, $key, $total, $userId, $notify) {
            $task = $key === null
                ? new BackgroundTask()
                : BackgroundTask::query()->firstOrNew([
                    'subject_type' => self::subjectType($type),
                    'subject_id' => $key,
                ]);

            $task->fill([
                'type' => $type,
                'label' => Str::limit($label, 250),
                'user_id' => $userId,
                'status' => BackgroundTask::STATUS_RUNNING,
                'done' => 0,
                'total' => $total,
                'message' => null,
                'result_url' => null,
                'result_label' => null,
                'notify' => $notify && $userId !== null,
                'started_at' => now(),
                'finished_at' => null,
            ]);
            $task->save();

            return $task;
        }, null, report: true);
    }

    /**
     * De lopende taak met deze sleutel (een nieuwe poging van dezelfde job,
     * of de taak die de starter van een reeks al opende), anders een nieuwe.
     */
    public static function open(string $type, string $label, ?string $key, ?int $total = null, ?int $userId = null, bool $notify = false): ?BackgroundTask
    {
        return self::find($type, $key) ?? self::start($type, $label, $key, $total, $userId, $notify);
    }

    public static function find(string $type, ?string $key): ?BackgroundTask
    {
        if ($key === null || ! self::available()) {
            return null;
        }

        return rescue(fn () => BackgroundTask::query()
            ->where('subject_type', self::subjectType($type))
            ->where('subject_id', $key)
            ->active()
            ->latest('id')
            ->first(), null, report: false);
    }

    /**
     * Eén onderdeel van de taak is klaar (of definitief mislukt, met $error).
     * Bij het laatste onderdeel sluit de taak af. Het ophogen gebeurt in de
     * database, want de jobs van een reeks kunnen naast elkaar lopen.
     */
    public static function advance(string $type, ?string $key, ?string $message = null, ?string $url = null, ?string $urlLabel = null, Throwable|string|null $error = null): void
    {
        $task = self::find($type, $key);
        if (! $task) {
            return;
        }

        rescue(function () use ($task, $message, $url, $urlLabel, $error) {
            $query = fn () => BackgroundTask::query()->whereKey($task->getKey());

            if ($error !== null) {
                $query()->update(['message' => Str::limit(__('Een onderdeel is mislukt: :fout', [
                    'fout' => $error instanceof Throwable ? $error->getMessage() : $error,
                ]), 500)]);
            }

            $query()->increment('done');
            $task->refresh();

            // Zonder totaal telt de starter nog: die sluit af via total().
            if ($task->total === null || $task->done < $task->total) {
                return;
            }

            // Alleen de job die de status omzet sluit af, zodat twee jobs die
            // tegelijk het laatste onderdeel melden niet allebei een melding sturen.
            $claimed = $query()
                ->where('status', BackgroundTask::STATUS_RUNNING)
                ->update(['status' => BackgroundTask::STATUS_COMPLETED]);
            if (! $claimed) {
                return;
            }

            if ($error !== null && $task->total <= 1) {
                $task->fail($error);

                return;
            }

            $task->finish(trim(($message ?? '').' '.($task->message ?? '')) ?: null, $url, $urlLabel);
        }, report: true);
    }

    /**
     * Zet het totaal van een reeks zodra de starter weet hoeveel jobs er echt
     * zijn gestart. Zijn die intussen allemaal al klaar (of is er niets
     * gestart), dan sluit de taak hier af.
     */
    public static function total(string $type, ?string $key, int $total, ?string $message = null, ?string $url = null, ?string $urlLabel = null): void
    {
        $task = self::find($type, $key);
        if (! $task) {
            return;
        }

        rescue(function () use ($task, $total, $message, $url, $urlLabel) {
            $query = fn () => BackgroundTask::query()->whereKey($task->getKey());
            $query()->update(['total' => $total]);
            $task->refresh();

            if ($task->done < $total) {
                return;
            }

            $claimed = $query()
                ->where('status', BackgroundTask::STATUS_RUNNING)
                ->update(['status' => BackgroundTask::STATUS_COMPLETED]);
            if ($claimed) {
                $task->finish(trim(($message ?? '').' '.($task->message ?? '')) ?: null, $url, $urlLabel);
            }
        }, report: true);
    }

    public static function fail(string $type, ?string $key, Throwable|string|null $error = null): void
    {
        rescue(fn () => self::find($type, $key)?->fail($error), report: false);
    }

    /**
     * Link naar een Filament-pagina; null als de pagina niet te bepalen is.
     *
     * @param  class-string  $resource
     * @param  array<string, mixed>  $parameters
     */
    public static function url(string $resource, string $page = 'index', array $parameters = []): ?string
    {
        return rescue(fn () => $resource::getUrl($page, $parameters), null, report: false);
    }

    protected static function subjectType(string $type): string
    {
        return 'marketing:'.$type;
    }
}

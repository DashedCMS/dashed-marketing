<?php

namespace Dashed\DashedMarketing\Support;

use Throwable;
use Dashed\DashedMarketing\Models\ContentDraft;
use Dashed\DashedMarketing\Filament\Resources\ContentDraftResource;

/**
 * Eén taak in het takenicoon voor het schrijven van een concept, ook als dat
 * uit een keten van jobs bestaat (secties, veelgestelde vragen, meta). De
 * starter van de keten roept start() aan met het aantal jobs; elke job meldt
 * zich met begin() en step(). Een losse job (één sectie opnieuw) is een taak
 * van één onderdeel.
 */
class ContentDraftTask
{
    public const TYPE = 'content-draft';

    public static function start(ContentDraft $draft, int $total, ?int $userId = null): void
    {
        MarketingTask::start(self::TYPE, self::label($draft), (string) $draft->getKey(), $total, $userId, notify: true);
    }

    public static function begin(?ContentDraft $draft): void
    {
        if ($draft === null) {
            return;
        }

        MarketingTask::open(self::TYPE, self::label($draft), (string) $draft->getKey(), 1);
    }

    public static function step(?ContentDraft $draft): void
    {
        if ($draft === null) {
            return;
        }

        MarketingTask::advance(
            self::TYPE,
            (string) $draft->getKey(),
            __('De inhoud van het concept is gegenereerd.'),
            MarketingTask::url(ContentDraftResource::class, 'edit', ['record' => $draft]),
            __('Bekijk concept'),
        );
    }

    /**
     * Een job uit de keten is definitief mislukt: de rest van de keten loopt
     * dan niet meer, dus de hele taak is mislukt.
     */
    public static function fail(int|string|null $draftId, Throwable|string|null $error = null): void
    {
        if ($draftId === null) {
            return;
        }

        MarketingTask::fail(self::TYPE, (string) $draftId, $error);
    }

    protected static function label(ContentDraft $draft): string
    {
        return __('Concept schrijven: :naam', ['naam' => $draft->name ?: $draft->keyword ?: '#'.$draft->getKey()]);
    }
}

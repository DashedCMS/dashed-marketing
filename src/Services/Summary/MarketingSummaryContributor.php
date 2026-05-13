<?php

declare(strict_types=1);

namespace Dashed\DashedMarketing\Services\Summary;

use Dashed\DashedCore\Services\Summary\Contracts\SummaryContributorInterface;
use Dashed\DashedCore\Services\Summary\DTOs\SummaryPeriod;
use Dashed\DashedCore\Services\Summary\DTOs\SummarySection;
use Dashed\DashedMarketing\Models\ContentDraft;
use Dashed\DashedMarketing\Models\SocialChannel;
use Dashed\DashedMarketing\Models\SocialPost;

/**
 * Samenvatting-bijdrage voor de marketing-module. Toont in de mail
 * het aantal gepubliceerde social-posts, het aantal gegenereerde
 * content-drafts en een tabel met posts per kanaal in de gekozen
 * periode.
 *
 * Marketing is geen dagelijks rapport, dus 'daily' wordt bewust niet
 * aangeboden in availableFrequencies.
 */
class MarketingSummaryContributor implements SummaryContributorInterface
{
    public static function key(): string
    {
        return 'marketing';
    }

    public static function label(): string
    {
        return 'Marketing';
    }

    public static function description(): string
    {
        return 'Gepubliceerde social-posts, gegenereerde content en kanaal-overzicht in de periode.';
    }

    public static function defaultFrequency(): string
    {
        return 'monthly';
    }

    public static function availableFrequencies(): array
    {
        return ['weekly', 'monthly'];
    }

    public static function contribute(SummaryPeriod $period): ?SummarySection
    {
        // Posts gepubliceerd in de periode. We kijken naar posted_at
        // (whereBetween op een geïndexeerde timestamp-kolom) en
        // beperken tot statussen die ook daadwerkelijk live zijn
        // gegaan, zodat partially_posted en concept-rommel niet
        // meegeteld worden.
        $publishedPosts = SocialPost::query()
            ->withoutGlobalScopes()
            ->whereIn('status', ['posted', 'partially_posted'])
            ->whereNotNull('posted_at')
            ->whereBetween('posted_at', [$period->start, $period->end])
            ->get(['id', 'site_id', 'channels', 'platform']);

        $publishedCount = $publishedPosts->count();

        // Gegenereerde content-drafts: alle drafts aangemaakt in de
        // periode, ongeacht of ze al toegepast zijn. Zo zien admins
        // ook drafts die nog in concept of ready staan.
        $generatedContent = ContentDraft::query()
            ->whereBetween('created_at', [$period->start, $period->end])
            ->count();

        if ($publishedCount === 0 && $generatedContent === 0) {
            return null;
        }

        $blocks = [
            [
                'type' => 'stats',
                'data' => [
                    'rows' => [
                        ['label' => 'Gepubliceerde social-posts', 'value' => (string) $publishedCount],
                        ['label' => 'Gegenereerde content-drafts', 'value' => (string) $generatedContent],
                    ],
                ],
            ],
        ];

        // Tel posts per kanaal. Een post kan op meerdere kanalen
        // tegelijk uitgaan, dus voor het overzicht "per kanaal"
        // willen we elke channel-slug één keer per post tellen.
        // Valt terug op platform als channels leeg is (legacy posts
        // van vóór de channels-migratie).
        if ($publishedCount > 0) {
            $perChannel = [];
            foreach ($publishedPosts as $post) {
                $channels = is_array($post->channels) ? $post->channels : [];
                if ($channels === [] && $post->platform) {
                    $channels = [$post->platform];
                }
                foreach ($channels as $channel) {
                    if (! is_string($channel) || $channel === '') {
                        continue;
                    }
                    $perChannel[$channel] = ($perChannel[$channel] ?? 0) + 1;
                }
            }

            if ($perChannel !== []) {
                arsort($perChannel);

                // Resolve channel-slugs naar leesbare namen via
                // dashed__social_channels. Alleen één query, niet
                // per rij, om N+1 te voorkomen.
                $channelNames = SocialChannel::query()
                    ->whereIn('slug', array_keys($perChannel))
                    ->pluck('name', 'slug')
                    ->all();

                $rows = [];
                foreach ($perChannel as $slug => $count) {
                    $label = $channelNames[$slug]
                        ?? config("dashed-marketing.platforms.{$slug}.label")
                        ?? $slug;
                    $rows[] = [(string) $label, (string) $count];
                }

                $blocks[] = ['type' => 'heading', 'data' => ['content' => 'Posts per kanaal']];
                $blocks[] = [
                    'type' => 'table',
                    'data' => [
                        'headers' => ['Kanaal', 'Aantal posts'],
                        'rows' => $rows,
                    ],
                ];
            }
        }

        return new SummarySection(
            title: 'Marketing',
            blocks: $blocks,
        );
    }
}

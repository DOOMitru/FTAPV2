<?php

namespace App\Support;

use App\Models\PokerSeason;
use App\Models\User;
use App\Models\VenuePoints;

/**
 * One season's figures for one player, as the props SeasonPanel.vue declares.
 *
 * The panel is drawn by the dashboard and by a player's profile, about
 * different people. Everything here is a decision rather than a number -- one
 * decimal place, thousands separators, two pluralisations, and the dash an
 * unranked player gets -- and spec §5a puts those in PHP. Built once, because
 * two copies of this would be free to disagree about what "8.4" means.
 */
class SeasonFigures
{
    /**
     * @return array<string, mixed>
     */
    public static function props(
        ?PokerSeason $currentSeason,
        string $subjectId,
        ?User $viewer,
        bool $self,
    ): array {
        return [
            // The season IS the title. It was a badge beside the button, under
            // a heading reading "Current Season" -- true of every season, and
            // so of no help in telling them apart.
            'season_name' => $currentSeason?->name ?? __('Current Season'),
            'season_href' => $currentSeason ? route('seasons.show', $currentSeason) : null,

            // Null rather than a zeroed row: the panel draws its empty state
            // from this, and a column of zeros would be a claim about the
            // player rather than a statement about the league.
            'season' => $currentSeason
                ? self::figures($currentSeason, $subjectId, $viewer)
                : null,

            'self' => $self,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function figures(PokerSeason $season, string $subjectId, ?User $viewer): array
    {
        $figures = PokerSeason::figuresFor($season, $subjectId);

        // The owner's rule, applied here so that no page has to remember it: a
        // player's venue tally is read by that player and by admins, nobody
        // else. Null means WITHHELD, and the panel leaves the row out
        // altogether -- a zero would be a fact about the player, and this is a
        // fact about the reader.
        $mayReadVenuePoints = VenuePoints::readableBy($viewer, $subjectId);

        return [
            'rank_label' => $figures['rank'] ? '#'.$figures['rank'] : '—',

            // Null rather than "0.0 pts per event": there is no average of no
            // events, and a zero reads as a bad one.
            'per_event_label' => $figures['perEvent'] === null
                ? null
                : number_format($figures['perEvent'], 1).' '.__('pts per event'),

            'totals_label' => trans_choice(
                '{1}:points pts over 1 event|[2,*]:points pts over :events events',
                $figures['events'],
                [
                    'points' => number_format($figures['points']),
                    'events' => number_format($figures['events']),
                ]
            ),

            'ranked_label' => $figures['rank']
                ? trans_choice('{1}of 1 ranked player|[2,*]of :count ranked players', $figures['ranked'])
                : null,

            // Small counts, printed as they are: the Blade twin does not
            // separate thousands here, and a player with a thousand wins is
            // not the case to design for.
            'first' => (string) $figures['first'],
            'second' => (string) $figures['second'],
            'third' => (string) $figures['third'],

            'events_label' => number_format($figures['events']),
            'points_label' => number_format($figures['points']),
            'venue_points_label' => $mayReadVenuePoints
                ? number_format($figures['venuePoints'])
                : null,
        ];
    }
}

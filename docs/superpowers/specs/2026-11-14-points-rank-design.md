# Season rank by points — design

**Date:** 2026-11-14 · **Branch:** `feature/remove-rank` · **Size:** small change

## Intent

A player's season rank is their place by **total points this season**, not
points per tournament entered. Every page that shows a rank shows the same one.

## The rule (decided by the owner)

1. **Ranked players:** those with **at least 1 point** this season. Anyone on 0
   is unranked (`—`), and is not counted in "of N ranked players".
2. **Order:** points, highest first.
3. **Tie on points:** more **venue points this season** (`season_id`-scoped,
   as everywhere else) goes first.
4. **Tie on both:** the players **share a place**, counted the way sports
   tables count: 1, 2, 2, 4. Each tied player's badge reads the shared place
   (`#2`). Tied players are listed alphabetically by their recorded name
   (`player_name`), so every page lists a tie in the same order. *(Builder's
   call at planning: the season page shows nicknames, and sorting each page
   by what it displays would let two pages disagree.)*

Kept from today, as builder's calls: a ranked player must also have
**entered** a tournament this season and have an **account**. The home card
shows **everyone placed 1-3**, so a tie at third shows all of them, not an
arbitrary three.

## Privacy — approach A, chosen by the owner

Venue points are owner-or-admin only. Breaking ties on them means the ORDER
and a shared place reveal how two tied players' venue points compare. The
owner accepted that, as one stated exception: **standings order and shared
places may reflect venue points; the figures themselves stay owner-or-admin
only.** Same shape as the finale indicator, which already tells everyone
whether a player has reached the venue-points threshold.

- The venue-points figure must not reach any payload it does not reach today.
  The ranking computes places internally and returns places, not venue
  points.
- The exception is written down in `VenuePointsPrivacyTest` (as the recorded
  reason), in `RESUME-HERE.md`'s privacy section, and in the
  venue-points-privacy memory.

**Considered and not taken:** (B) break ties on tournament wins, keeping the
rule absolute; (C) no tie-break, a tie on points simply shares a place.

## What changes, page by page

- **One definition stays one definition.** `PokerSeason::rankings()` is
  rewritten to the rule above and returns each row's `place`. The per-event
  ratio is removed from it and from `figuresFor()`.
- **Dashboard and player profile — the Season Rank panel.** `#N` is the shared
  place. The "38.2 pts per event" line is removed; the panel shows the totals
  ("120 pts over 6 events") and "of 41 ranked players". Unranked copy:
  "No points yet" with "Score points to be ranked." on your own dashboard, and
  "A player is ranked once they score points." on someone else's profile.
- **The full season page.** The **Points / Rank toggle is removed**; the
  standings have one order, the rule above, and the Rank column shows each
  row's place, with shared places. The figure column is Points. **Players on 0
  points are not listed at all** (owner's amendment at approval). The
  Players tile is counted from the listed rows, as it is today, so the tile
  and the table keep agreeing -- it now counts ranked players. A stale
  `?order=rank` link still loads the page, as an unknown order already does.
- **The home page (signed in).** The "Season Rank" and "Most Points" cards
  become the same list, so they merge into one **Season Rank** card, built
  from `rankings()` (shared places and all) and showing each player's points.
  "Most Wins" stays. Two cards instead of three.

## Non-goals

- No change to points, venue points, the finale rules or the points structure.
- No new "tied" marker beyond the shared number.
- No change to who may see a venue-points figure.

## Left to the builder

- Results with no linked account cannot be ranked, because `rankings()` keys
  on `user_id`, as it does today. On the season page such a row, if it has
  points, is listed unranked (`—`) below the ranked rows. Builder's call if
  a cleaner treatment falls out.
- The home grid going from three cards to two: check the layout and the
  `UnusedCssClassTest` fallout.
- Tests that encode the per-event rule are rewritten, not deleted, so each
  guarantee (one definition, the self-row, ordering, the panel's copy)
  survives under the new rule. New tests cover the 0-point cut, the venue
  tie-break, the shared place (1, 2, 2, 4), and the venue figure staying out
  of every payload.

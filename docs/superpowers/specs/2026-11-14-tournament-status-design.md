# Tournament status badges — design (short)

**Date:** 2026-11-14 · **Size:** small change · **Owner-approved approach:** A

## Intent

On a list of tournaments, a reader should see at a glance which nights are
still to come, which are over and waiting on results, and which have their
results out. Both admins (to spot nights still owing results) and players (to
see where things stand) read it. Today a row shows only name, venue and date.

## Scope

- **Poker Tournaments** page (`Poker/Tournaments/Index.vue`, the current
  season's list).
- **Venue page**, the "Recent Tournaments" card (`Poker/Venues/Show.vue`).
- Not the public Events page, the season page or the dashboard.

## The three states

Decided once, in PHP, from fields the tournament already has:

| State | Rule | Badge |
|---|---|---|
| Results published | `published_at` is set | plain (grey) — settled, needs nothing |
| Awaiting results | not published, `start_time` ≤ now | red (`primary`) — the one that should catch the eye |
| Upcoming | not published, `start_time` > now | green (`open`) — the app's colour for an open state |

Published is checked first, so it wins whatever the date says. A night being
played right now is "Awaiting results" (owner-accepted call): true from the
moment it starts until results go out. A past night that nobody entered and
nobody published stays "Awaiting results" — which is what it is.

## Approaches considered

- **A. A status badge on every row** — chosen. Word plus colour, so it reads
  without colour vision and in the phone card layout; order and pagination
  unchanged.
- B. Three sections per list — breaks the one timeline, and the venue page's
  ten-row card would show near-empty sections.
- C. A coloured edge stripe with a legend — colour alone, a key to learn, and
  lost in the phone layout.

## How

- `PokerTournament::status(): string` returns `'upcoming' | 'awaiting' |
  'published'` — one definition, used by both controllers.
- Both pages receive `status` per row; one small Vue component maps it to the
  badge variant and the words ("Upcoming", "Awaiting results", "Results
  published"), so the two pages cannot word or colour it differently.
- Existing `Badge.vue` variants only; no new colour. The badge CSS comment that
  calls `primary` an identity marker gains the exception.

## Left to the builder

Where the badge sits in each row (beside the name is the intent), and the
phone-layout fit; the browser sweep checks for horizontal overflow.

# Undo the last elimination

**2026-11-13. Short design — a small change.**

## Intent

An administrator running a tournament night can take back the most recent
elimination, putting that player back in the field. Pressed repeatedly it walks
back as far as the night needs. Only while results are unpublished.

**Why:** players go out in a different order than the screen says — the wrong
name is tapped, or someone says "no, Dave went out before me" a few hands
later. Today there is no way to correct it: `eliminate` only ever adds, and the
result create/update/delete routes went with the four admin screens removed in
September. The league's standings are built on these places, so a wrong order
is wrong points.

## Goals

- Undo the most recent elimination, one at a time, repeatable.
- The points land exactly as if the elimination had never happened.
- Refuse clearly when the undo is not safe, rather than doing something near-right.

## Non-goals

- Re-ordering places directly, or editing a finish in place.
- Undoing anything once results are published.
- A general result editor.

## Anti-goals

- A blind "delete the last row" that can remove a finish the administrator never
  saw on their screen.
- A second place-or-points calculation living beside the one in `eliminate()`.

## Constraints

- **Published is final.** The existing `PokerTournament::publishedRefusal()` is
  the one statement of that rule; this adds a seventh call site, not a new rule.
- **Places are handed out from the bottom up.** `eliminate()` derives place as
  `registrants − results`, so the most recent elimination is the row with the
  **lowest place on record** — not the newest timestamp. Late registration
  shifts places, so `place` is the authority and `created_at` is not.
- Admin-only, like `eliminate`.
- No browser tests in the suite; anything only a browser can see is verified by
  `bin/sweep.sh` or by hand.

## Approaches

1. **Delete the lowest-place result row.** *(chosen)* Deleting the lowest place
   leaves every other finish untouched and returns `registrants − results` to
   what it was, so the next elimination takes the same place and the same points
   back. No shifting, no repricing — the arithmetic that already runs does the
   work.
2. **Let the administrator re-order places directly** (set a place, or drag the
   list). Fixes a wrong order in one move rather than N undos, but needs a full
   `repriceForTournament()` on every edit, can put the field into states the
   night never passed through, and the one-at-a-time behaviour was asked for
   explicitly. Rejected.
3. **Restore generic result create/update/delete.** Reopens the surface removed
   in September for one specific, safe operation. Rejected.

## The design

**Route.** `DELETE tournaments/{tournament}/eliminate`, named
`tournaments.uneliminate`, beside the existing `POST .../eliminate` — the same
pairing `publish`/`unpublish` already uses on one URI.

**Body.** `user_id`, required, exactly as `eliminate` takes. The client names
the player it believes is last.

**The controller:**

1. `publishedRefusal()` — refuse if published.
2. Find that player's result in this tournament. None → refuse.
3. **Refuse unless that result holds the lowest place on record.** This is the
   stale-page guard: two administrators on two phones, or one page left open,
   otherwise means the row deleted is not the row the administrator saw.
   `eliminate()` already learned this with its unique-constraint catch.
4. Delete the row. Nothing else: no reprice, no shift. Any other finish keeps
   the place it holds, and the freed place is the next one `eliminate()` hands
   out.

**Flash.** A success says who is back in and which place is open again; each
refusal says why, in the voice `eliminate()` uses. The shell draws it as a
toast; the page draws nothing of its own.

**UI.** On the eliminated player's row in `Poker/Tournaments/Show.vue`, beside
the points badge, and only on the single row that can be undone. Confirmed
through `useConfirm`, naming the player and the points being given up — the
mirror of `eliminate_confirm`. Two new row fields from the controller:
`can_undo` and `undo_confirm`.

## Calls made here, not asked about

- **Hard delete, no audit trail.** Nothing has been announced before publish —
  `TournamentPlacement` only goes out from `publish()` — and the next
  `eliminate` writes the identical row back. A trail would record a correction
  nobody outside the room ever saw.
- **Undo stays available on a complete field.** All players out but unpublished
  is exactly when a wrong order gets noticed. It makes the tournament incomplete
  again, which `isComplete()` already reports correctly.
- **Nothing downstream needs touching.** `podium()` reads results live and
  follows on its own; venue points are their own record, not derived from
  results; the publish offer is a flashed flag, gone on the next request.

## Left to the builder

- Exact wording of the flash and confirmation sentences, in the voice of the
  surrounding ones.
- Where the "lowest place" lookup lives — controller or a small method on the
  tournament — given six call sites already reach through `publishedRefusal()`.
- Which guard tests to add, following the project's habit of encoding a decision
  as a test rather than remembering it.

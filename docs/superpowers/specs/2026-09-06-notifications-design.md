# Player notifications

**Status:** approved design, not yet implemented.
**Date:** 2026-09-06.

Players are told, inside the app, when a tournament they scored in is finished
and what they earned. Read state and deletion are theirs to manage. The system
is general — a placement is the first kind of notification, not the only kind it
can carry.

## The problem this design had to solve first

"Notify people once all the results are in" is not a stable condition in this
app. Two mechanisms move a finished field:

- Registering a late player shifts every recorded finish down. That is
  deliberate — `PokerTournamentRegistrant`'s shift hook exists because a place
  is a position in a field, so a field of ten becoming eleven makes a recorded
  tenth into an eleventh.
- A result can be edited or deleted through the admin results CRUD.

So a player told they came first can genuinely become second afterwards, with
nothing anywhere disagreeing. Firing automatically on "every registrant has a
result" would send true statements that quietly become false.

**Decision:** an administrator publishes results explicitly, and publishing
locks the tournament.

## 1. Storage

Laravel's own database notifications. `User` already has `Notifiable`; only the
table is missing. This gives `read_at`, `markAsRead()`, `markAsUnread()`,
`unreadNotifications` and per-row `delete()` without writing any of it, and it
makes the store general — the placement notification is a `type`, and a second
kind of notification later needs no schema change.

A bespoke table with typed columns was considered and rejected: it buys nothing
here and gives up the framework's read-state handling.

**The one trap.** Laravel's stock `notifications` migration declares
`$table->morphs('notifiable')`, which is an unsigned bigint. Users in this app
are ULIDs. The column must be `$table->ulidMorphs('notifiable')`. A `morphs`
here migrates cleanly and then fails on the first insert, so a test must cover
an actual send rather than the schema alone.

```
notifications
  id            uuid, primary
  type          string           the notification class
  notifiable    ulidMorphs       notifiable_type + notifiable_id (string)
  data          text (json)
  read_at       timestamp, null
  timestamps
```

## 2. Publishing

New column: `tournaments.published_at`, nullable timestamp.

**`PokerTournament::isComplete()`** — has at least one registrant, and every
registrant has a result. Guards against the empty tournament, which otherwise
reads as trivially complete.

**`PokerTournament::isPublished()`** — `published_at` is not null.

**`POST /poker/tournaments/{tournament}/publish`** (admin). Refused unless the
tournament is complete and not already published. Sets `published_at` and sends
one `TournamentPlacement` notification to every result with `points > 0`.

**`DELETE /poker/tournaments/{tournament}/publish`** (admin) unpublishes:
clears `published_at` **and deletes the placement notifications it sent for that
tournament**.

That deletion is deliberate. Unpublishing means the results were not final, so
leaving a message that says "you finished 1st" when the league no longer thinks
so is leaving a false statement in a player's inbox. It also means republishing
sends one clean set rather than a duplicate for everyone whose placing did not
change. The cost is that a notification can disappear from a player's list; that
is the correct behaviour for a retracted claim.

**A tournament with no points structure publishes and notifies nobody.** Every
result scores 0, so nobody clears the `points > 0` bar. Publishing still
proceeds, because locking is the other half of what it does and refusing would
leave the tournament permanently unfinishable. The flash message reports the
count, so "0 players notified" is visible rather than silent.

## 3. The lock

Once published, six paths refuse:

| Path | Controller |
| --- | --- |
| register (self and admin override) | `PokerTournamentController@register` |
| unregister | `PokerTournamentController@unregister` |
| eliminate | `PokerTournamentController@eliminate` |
| remove registrant | `PokerTournamentRegistrantController@destroy` |
| create result | `PokerTournamentResultController@store` |
| update / delete result | `PokerTournamentResultController@update`, `@destroy` |

The check lives in **one** method on `PokerTournament` so six call sites cannot
drift apart — the same reasoning that put `hasRecordedResults()` on the model.
The refusal names the tournament and says to unpublish first.

Note the ordering against the existing rule: `hasRecordedResults()` already
refuses withdrawal and registrant removal once any finish is recorded. The
published check is a second, stricter gate, not a replacement.

**Two of the six are already refused by that older rule**, and this is worth
knowing before someone deletes them as dead code. A published tournament has a
result for every registrant, so `hasRecordedResults()` is necessarily true and
unregister and registrant-removal were already being turned away. What the
published check adds there is the *reason*: "results have been published" is
what an administrator needs to hear, not "results have been recorded". Those two
tests therefore assert the message, not merely the refusal.

## 4. The notification

`App\Notifications\TournamentPlacement`, `via: ['database']` only. No email:
this is an in-app system, and a mass mail on every league night is a different
decision with a different cost.

`toArray()` carries what the card needs without a join back to a tournament that
may later be edited or deleted:

```
tournament_id, tournament_name, played_on, place, points
```

**Recipients: every result with `points > 0`**, not the top three. The cut is
the points structure — a structure paying the top ten of a field of twenty
notifies ten people, and eleventh onwards score nothing and hear nothing.

**Fanfare is tiered**, because "appropriate" has to mean something for fourth:

- **1st, 2nd, 3rd** — the medal treatment, reusing `--c-gold`, `--c-silver` and
  `--c-bronze`, so the notification matches the podium the player just saw.
- **4th and below with points** — the same card in the accent red. Still worth
  telling someone about; it does not claim a medal it did not win.

## 5. Reading, unreading, deleting

`NotificationController`:

- `PATCH /notifications/{notification}` — **toggles** read/unread. One control,
  one route: the button says "Mark as read" or "Mark as unread" depending on
  where the row stands, and posts the same request either way.
- `DELETE /notifications/{notification}` — deletes, **refused unless read**.
  Deleting something you have not looked at is how a player loses a result they
  never saw.
- `DELETE /notifications/read` — clears all read notifications at once.

**Route order matters.** `/notifications/read` must be declared before
`/notifications/{notification}`, or the literal path is swallowed by the
parameter. Notification ids are UUIDs so a real collision is impossible, but the
ordering bug is silent and easy to introduce later — a test hits the literal
route and asserts it clears rather than 404s.

Every one is scoped to the authenticated user's own notifications; a route model
binding that could reach another player's row is the obvious hole here and must
be closed by scoping the query, not by a check after the fact.

Destructive actions use the app's existing `data-confirm` dialog.

## 6. Interface

### Desktop

The user dropdown becomes a two-region panel:

```
+-------------------------------+
|  NOTIFICATIONS          [3]   |
|  +-------------------------+  |
|  | 1st - Autumn Showdown   |  |  <- gold edge, unread
|  | 100 pts - Sep 9         |  |
|  |            [read] [x]   |  |
|  +-------------------------+  |
|  | 5th - Summer Open       |  |  <- accent edge, read
|  | 40 pts - Aug 12         |  |
|  +-------------------------+  |
|  ...scrolls, newest first     |
|                               |
|  Clear read notifications     |
+-------------------------------+
|  Your profile                 |
|  Log out                      |
+-------------------------------+
```

Capped at the 10 most recent; the list scrolls within the panel. The unread
count sits as a badge on the trigger's top-right corner.

### Mobile

The mobile topbar has **no dropdown today** — it is a flat row of Log out, theme
toggle and a profile link. This is new construction, not a modification.

A bell button, icon only, goes **left of the burger** in `topbar__inner`,
carrying the same badge and opening its own panel with the same list. The
existing flat row is untouched.

`x-topbar` is shared with the public shell, so the bell arrives through a new
optional slot rendered before the burger. The public shell passes nothing and is
unchanged.

Visibility mirrors the existing split: the bell is mobile-only (the desktop
notifications live in the user dropdown), the way `topbar__actions-mobile` and
`topbar__actions-desktop` already work, at the 48rem breakpoint the dropdown
component already uses.

## 7. Data loading

The badge needs an unread count on every authenticated page. One
`unreadNotifications()->count()` per request, read in the layout. The list
itself loads only when a panel is rendered.

## 8. Constraints this must honour

- **No inline CSS.** `InlineStyleGuardTest` enforces it; the only exemption is
  `resources/views/vendor/mail`.
- **No inline JavaScript.** Alpine directives in attributes, as `x-dropdown` and
  `x-topbar` already do.
- Borders rather than shadows in the dashboard register; gradients,
  `--shadow-raised`, `--shadow-float` and `--radius-lg` stay fenced to
  `5-public/` (`PublicRegisterTest`).
- **No browser-based tests.** Anything visual is verified by screenshot.

## 9. Testing

- **The ULID trap:** a real send, asserting the row exists and belongs to the
  user. A schema-only test passes against a broken `morphs`.
- **Recipients:** exactly the `points > 0` results are notified — including a
  fourth-place finisher who scored, and excluding a scoring place that paid 0.
- **Publish guards:** refused when incomplete, refused when already published,
  allowed when complete.
- **The lock:** each of the six paths refuses after publishing, and each is
  allowed before it. Six pairs, because a blanket refusal would pass the first
  half of every one of them. Two of the six assert the published message
  specifically, since the older rule would refuse them anyway.
- **Route order:** `DELETE /notifications/read` clears read notifications rather
  than 404ing as an unmatched id.
- **Unpublish:** clears the flag, deletes that tournament's placement
  notifications, and leaves another tournament's notifications alone.
- **Republish** sends one set, not two.
- **Ownership:** a player cannot read, unread or delete another player's
  notification.
- **Delete refuses an unread notification**, and `clear read` leaves unread ones.
- **The badge** counts unread only, and disappears at zero.
- **Both surfaces** render: the desktop dropdown and the mobile bell panel.

## 10. Deliberately out of scope

A standalone `/notifications` page, email or push delivery, notifying
non-scoring finishers, and per-player notification preferences.

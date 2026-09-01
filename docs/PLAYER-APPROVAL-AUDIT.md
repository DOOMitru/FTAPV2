# Player approval — exit audit

**2026-09-01.** Anyone may register an account. Nobody may enter a tournament
until an administrator approves them, and administrators can create
pre-approved players directly.

**Spec:** `docs/superpowers/specs/2026-09-01-player-approval-design.md`
**Plan:** `docs/superpowers/plans/2026-09-01-player-approval.md`

## Result

| | |
|---|---|
| Suite | **169 passed** (was 139 before this work) |
| New tests | 30 |
| Accounts grandfathered | 102, with no decider recorded |
| Registration paths gated | **4** — the plan named 2 |

## The walk-through, end to end over real HTTP

1. Registered through `/register` → `approval: pending`, `verified: no`
2. `/events` renders **"Awaiting approval"**, and no register form action
3. `/dashboard` → 302 to `/verify-email` — the *other* gate, in a different place
4. POSTing to `tournaments.register` anyway → *"waiting for approval by a league
   administrator, so you cannot enter tournaments yet"*
5. The account appears in the administrator's queue
6. Approving sets `approved` and records the decider
7. The player then sees a register control

## The two gates report themselves distinctly

This was the risk the spec flagged, because a player can now be blocked by
approval, by verification, or by both, and an unexplained refusal turns support
into guesswork.

| blocked by | where it happens | what the player sees |
|---|---|---|
| email verification | `/dashboard` | redirect to `/verify-email` |
| approval | tournament registration | named error message |
| both | different pages | no contradiction — they never collide |

## Four registration paths, not two

The plan enumerated two. Reading the code found two more, each of which would
have left the rule bypassable:

1. `PokerTournamentController@register` — self-service **and** the administrator
   `user_id` override, which share one method. The gate reads the **target**
   user, not the actor; checking the actor would have made an administrator a
   way *around* the rule rather than a user of it.
2. `PokerTournamentRegistrantController@store`
3. **`PokerTournamentRegistrantController@update`** — not in the plan. Identical
   validation, so an edit could have reassigned a registration to an unapproved
   account.
4. **`PokerTournamentController@show`'s `$availableUsers`** — not in the plan.
   A third picker, on the tournament page rather than the registrant form,
   offering every account to the administrator override. Missed initially
   because it does not live in the registrant controller.

All three pickers now filter to `approved()`. A form that offers a choice its
own store would refuse is a worse failure than one that never offers it.

## What the tests caught that review would not have

- **`users.id` is a ULID stored as varchar**, so `approval_decided_by` is a
  string. The obvious `foreignId()` would not have matched the column it
  references.
- **A database default is never hydrated back.** A freshly created `User` had a
  null `approval_status`: `isApproved()` failed closed, correctly, but
  `isPendingApproval()` was also false — an account in no state at all until it
  was reloaded. Fixed with a model-level default.
- **`Password::createToken()` calls `deleteExisting()` unconditionally.** Sending
  a reset link and then creating a token to surface a copy would have **emailed
  a link the second call had already killed** — and a test asserting only that
  a session key was set would have passed. Both invite paths issue one token and
  use it twice, and the tests assert `Password::tokenExists()`.
- **`.l-stack > * + *` sets margin on CHILDREN.** Putting it on a flex cluster
  would have offset each button vertically instead of spacing the group.

## Deliberate decisions worth not re-litigating

- **Rejection keeps the account.** Deleting would make the decision
  unrecoverable, lose the record that it was made, and let the same person
  re-register into a clean slate. The main table carries an approval column and
  `users.show` carries the controls, because a rejected account has left the
  queue and would otherwise have no route back.
- **The invite is a password-reset link**, not a new mechanism. The framework
  already issues signed, expiring, single-use tokens and the app carries the
  whole flow.
- **Admin creation confers approval only.** The player still verifies their own
  address.
- **The invite action is offered unconditionally.** Whether an account has ever
  set a password is not knowable from the schema: every account has a hash, and
  the random one a new player starts with is indistinguishable from a chosen one.
- **A rejected account can still log in.** Approval gates tournament entry, not
  authentication.

## Still open

1. **`MAIL_MAILER=log`.** Nothing sends. Both the invite and the verification
   link are inert as email; the copyable links in the admin UI are what make the
   feature usable today, and they remain useful afterwards for when a player
   says the mail never arrived. **A real mailer is needed before this ships.**
2. **No notification when a player is approved or rejected.** Out of scope by
   the spec; needs a mailer and a decision about tone.
3. The four items in `docs/RED-BLACK-EXIT-AUDIT.md`, unchanged by this work.

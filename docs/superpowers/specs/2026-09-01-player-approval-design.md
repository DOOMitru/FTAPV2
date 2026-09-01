# Player approval — design

**2026-09-01.** Anyone can register for an account. Nobody can enter a
tournament until an administrator has approved them. Administrators can also
create players directly, and a player created that way is approved on the spot.

## Why

Registration is currently open and unconditional: an account created through
`/register` can immediately self-register for any tournament whose registration
window is open. For a league whose events are free to enter and run at partner
venues with finite seats, that is a door with no lock on it — there is no point
at which a person is admitted to the league as opposed to admitted to the
website.

This adds that point, and nothing else. It does not restrict reading the site,
viewing standings, or using the dashboard.

## Decisions taken

All four by the owner on 2026-09-01:

1. **Approval and email verification stay independent.** They answer different
   questions — "is this address real?" and "should this person play?" — and
   coupling them would make the pending queue misreport whenever mail is down.
2. **An admin-created player receives an emailed invite link** to set their own
   password, rather than the admin choosing one. See "The mail dependency".
3. **Rejection keeps the account, marked rejected.** It leaves the queue without
   vanishing, the decision is reversible, and someone who was refused cannot
   silently create a duplicate by re-registering.
4. **An admin-created player still verifies their own email.** Admin creation
   grants approval only.

Plus one addition, requested after the design was presented: **administrators
can resend or copy a player's verification link**, the same way they can the
invite link.

## 1 · The state

Three columns on `users`:

| column | type |
|---|---|
| `approval_status` | `enum('pending','approved','rejected')`, default `'pending'`, indexed |
| `approval_decided_at` | nullable timestamp |
| `approval_decided_by` | nullable FK → `users.id`, `nullOnDelete` |

**An enum rather than two nullable timestamps.** The obvious alternative —
`approved_at` and `rejected_at`, mirroring `email_verified_at` — represents three
states with two nullable columns, which admits a fourth, meaningless combination
where both are set. An enum cannot express a state that does not exist.

`approval_decided_by` is what makes rejection reversible in practice rather than
in theory: an administrator reversing a decision can see whose it was.

**Existing accounts are backfilled to `approved`.** All 102 of them registered
when no approval step existed and cannot be expected to have satisfied one. This
is the same grandfathering the email-verification migration performed, for the
same reason, and `approval_decided_by` is left null because no person made that
decision.

### Model surface

```php
public function isApproved(): bool;      // approval_status === 'approved'
public function isPendingApproval(): bool;
public function scopeAwaitingApproval($query);
public function scopeApproved($query);
```

`isApproved()` is the single expression of the rule. Every gate below calls it
rather than comparing the column, so the rule has one definition.

## 2 · The gate

Three places can create a tournament registration. **All three are gated**,
because the requirement is that a player cannot register *or be registered*.
Gating only self-service would leave an administrator able to seat someone the
league has not admitted.

| call site | what changes |
|---|---|
| `PokerTournamentController@register` | covers self-service **and** the admin `user_id` override in the same method; both paths check `isApproved()` on the *target* user, not the actor |
| `PokerTournamentRegistrantController@store` | admin path; validates that `user_id` refers to an approved account |
| `PokerTournamentRegistrantController` create/edit pickers | `User::all()` at lines 29 and 62 becomes an approved-only scope, so an administrator is never offered a choice the store would then refuse |

The picker change matters as much as the guards: a form that offers an option it
will reject is a worse failure than one that never offers it.

## 3 · The UI

**Where a register control renders today, a pending player sees a notice.** The
button does not appear in a state that would fail. This affects the events page
and the tournament show page, both of which key off `registration_open`.

**User management gains a pending queue.** A section above the main table,
rendered only when it is non-empty, listing accounts awaiting a decision with
**Approve**, **Reject** and **View**. Administrators only — the page is already
behind `EnsureUserIsAdmin`, so this needs no new authorisation, but the section
is part of an admin-only page by construction rather than by a separate check.

**The main table gains an approval column**, so the queue is not the only place
approval state is visible. This is what makes rejection reversible in fact
rather than in principle: a rejected account has left the queue, so without a
status on the main list there is no route back to it. From `users.show` an
administrator can approve a pending *or rejected* account, and reject an
approved one. The queue is a convenience for the common case, not the only
control surface.

**A Register Player button** sits in the page header, alongside the standardised
action buttons the other index pages carry.

`users.create` and `users.store` **do not currently exist** — they were removed
in Phase 0 as dead routes whose controller methods were missing and which
returned HTTP 500. Both come back, with real methods this time.

## 4 · Admin-created players

The form takes first name, last name, nickname, email and the admin flag. **It
has no password field.** On store:

1. the account is created with a random, unusable password;
2. `approval_status` is set to `approved`, with `approval_decided_by` set to the
   administrator who created it;
3. a password-reset link is sent as the invite.

**The invite is a password-reset link, not a new mechanism.** Laravel's password
broker already issues signed, expiring, single-use tokens and the app already
carries the whole flow — `password.request`, `password.email`, `password.reset`,
`password.store`. Inventing a parallel invite-token system would mean a second
table, a second expiry policy and a second set of security assumptions, to do
something the framework already does.

## 5 · The mail dependency, stated plainly

`MAIL_MAILER=log`. Nothing sends. Two of the decisions above depend on email —
the invite link and email verification — so an administrator creating a player
today would produce an account with **no password and no verified address**,
which nobody can get into.

The owner chose these options knowing that, and they are the right long-term
design. So the mitigation is to make them work now rather than to change them:

- **After creating a player, the invite link is displayed to the administrator**,
  copyable, in the flash message.
- **`users.show` carries two administrator actions** — resend/copy the invite
  link, and resend/copy the verification link. The invite action is offered only
  while the account has never set a password; the verification action only while
  the address is unverified. An action that cannot do anything should not be
  drawn.

Both are signed URLs either way; showing one costs nothing that emailing it does
not already cost. `EmailVerificationNotificationController@store` cannot serve
the second: it acts on `$request->user()`, the authenticated actor, so an
administrator acting on someone else's account needs its own route.

**This does not remove the need for a mailer.** It makes the feature usable
before one exists, and it stays useful afterwards as a fallback when a player
says the mail never arrived.

## 6 · What proves it

- the backfill approves exactly the pre-existing accounts, and sets no decider
- a pending player is refused by `tournaments.register`
- a pending player is refused when an administrator registers them via `user_id`
- a pending player is refused by the admin registrant path
- a **rejected** player is refused by all three, not merely absent from the queue
- an approved player is admitted by all three
- the registrant picker omits pending and rejected accounts
- approve and reject record `approval_decided_at` and `approval_decided_by`
- the pending queue renders for an administrator and 403s for a player
- a rejected account can be approved from `users.show`, which is the only route
  back once it has left the queue
- the queue is absent, not empty-stated, when nothing is pending
- an admin-created player is approved, unverified, and has no usable password
- the invite and verification links shown to an administrator are valid signed
  URLs that actually work when followed

## 7 · Risks

- **Two gates on one user.** A player can now be blocked by verification, by
  approval, or by both, and the reasons are different. Every blocked state needs
  to say which, or support becomes guesswork.
- **The register button and the guard can disagree.** The button is hidden on
  `isApproved()` and the controller refuses on `isApproved()`; if those ever
  diverge a player sees a control that fails. Both read the same model method
  for that reason, and the tests exercise the controller directly rather than
  only the rendered page.
- **`RouteSmokeTest` sweeps every GET route as a player.** The factory produces
  approved users by default, as it produces verified ones, so existing tests are
  unaffected — but the default matters and should be explicit in the factory.
- **A rejected account can still log in.** Rejection gates tournaments, not
  authentication. That is deliberate; a rejected person seeing the public site
  and their own profile is not a problem, but it should be a decision on record
  rather than an oversight.

## 8 · Out of scope

- Notifying a player that they were approved or rejected. Needs a mailer and a
  decision about tone; not required for the gate to work.
- Any change to who can *log in*. Approval gates tournament entry only.
- The four items still open in `docs/RED-BLACK-EXIT-AUDIT.md`.

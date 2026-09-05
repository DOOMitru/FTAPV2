# Player Approval Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Anyone may register an account, but nobody may enter a tournament until an administrator approves them — and administrators can create pre-approved players directly.

**Architecture:** One new enum column on `users` plus two audit columns, one model method that every gate calls, guards at all three code paths that can create a registration, and a pending queue on the existing user-management page. The admin invite reuses Laravel's password-reset broker rather than introducing a second token system.

**Tech Stack:** Laravel 12 · PHP 8.5 · SQLite · Blade + Alpine.js · PHPUnit 11

**Spec:** `docs/superpowers/specs/2026-09-01-player-approval-design.md`

## Global Constraints

- **NEVER RUN GIT COMMANDS.** Not `commit`, not `add`, not read-only `show`/`log`/`diff`. The owner runs every git operation by hand. Every "commit" step below is a **hand-off**: state the files and the message, then stop. To see what changed since the last commit use `find . -newer .git/COMMIT_EDITMSG -type f -not -path './node_modules/*' -not -path './vendor/*'`.
- **`isApproved()` is the only expression of the rule.** No gate compares `approval_status` directly. If the button and the guard ever read different things, a player sees a control that fails.
- **All three registration paths are gated**, because the requirement is that a player cannot register *or be registered*.
- **No inline CSS and no inline JavaScript.** Enforced by `InlineStyleGuardTest`. Confirmation dialogs use `data-confirm`, read by `resources/js/confirm.ts`.
- **Blocked states must say which gate blocked them.** A player can now be stopped by verification, by approval, or by both. "You cannot do this" without a reason turns support into guesswork.
- **Every new guard test must be proven to fail** before it is trusted — remove the guard, watch the test go red, restore it.
- **Existing accounts are grandfathered**, exactly as the email-verification migration did.

## Verification commands

```bash
php artisan test                  # 139 passing before this plan starts
php artisan migrate               # SQLite; the app DB has 102 real users
npm run build
```

## File structure

| File | Responsibility | Task |
|---|---|---|
| `database/migrations/*_add_approval_to_users_table.php` | the three columns, plus the backfill | 1 |
| `app/Models/User.php` | `isApproved()`, `isPendingApproval()`, scopes, cast | 1 |
| `database/factories/UserFactory.php` | approved by default; `pending()` and `rejected()` states | 1 |
| `app/Http/Controllers/Poker/PokerTournamentController.php:114` | gate the self and admin-override paths | 2 |
| `app/Http/Controllers/Poker/PokerTournamentRegistrantController.php:29,62` | gate the admin path; filter both pickers | 2 |
| `resources/views/events.blade.php`, `poker/tournaments/show.blade.php` | pending notice in place of the register control | 3 |
| `app/Http/Controllers/UserController.php` | `create`, `store`, `approve`, `reject`, `sendInvite`, `sendVerification` | 4, 5, 6 |
| `routes/web.php` | restore `users.create`/`users.store`; add the four admin actions | 4, 5, 6 |
| `resources/views/users/create.blade.php` | the Register Player form | 5 |
| `resources/views/users/index.blade.php` | pending queue, approval column, Register Player button | 4, 5 |
| `resources/views/users/show.blade.php` | approve/reject controls and the two link actions | 4, 6 |
| `tests/Feature/PlayerApprovalTest.php` | the gate | 2 |
| `tests/Feature/UserManagementTest.php` | the queue, creation, and the link actions | 4, 5, 6 |

---

### Task 1: The approval state

**Files:**
- Create: `database/migrations/<timestamp>_add_approval_to_users_table.php`
- Modify: `app/Models/User.php`
- Modify: `database/factories/UserFactory.php`
- Test: `tests/Feature/PlayerApprovalTest.php` (new)

**Interfaces:**
- Produces: column `approval_status` (`'pending'|'approved'|'rejected'`), `approval_decided_at`, `approval_decided_by`; methods `User::isApproved(): bool`, `User::isPendingApproval(): bool`; scopes `awaitingApproval()`, `approved()`; factory states `pending()`, `rejected()`. Every later task consumes these by name.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/PlayerApprovalTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_account_starts_pending(): void
    {
        // Created directly rather than through the factory: the factory
        // deliberately produces approved users so the other 16 test files are
        // unaffected, which means it cannot also prove the column's default.
        $user = User::create([
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.com',
            'password' => 'irrelevant',
        ]);

        $this->assertSame('pending', $user->fresh()->approval_status);
        $this->assertFalse($user->isApproved());
        $this->assertTrue($user->isPendingApproval());
    }

    public function test_the_factory_produces_approved_users_by_default(): void
    {
        // 16 test files create users and then hit routes that will be gated.
        // If this default ever flips they all break at once, so it is asserted
        // rather than assumed.
        $this->assertTrue(User::factory()->create()->isApproved());
    }

    public function test_the_factory_can_produce_pending_and_rejected_users(): void
    {
        $this->assertTrue(User::factory()->pending()->create()->isPendingApproval());

        $rejected = User::factory()->rejected()->create();
        $this->assertFalse($rejected->isApproved());
        $this->assertFalse($rejected->isPendingApproval());
    }

    public function test_scopes_select_the_right_accounts(): void
    {
        User::factory()->create();
        User::factory()->pending()->create();
        User::factory()->rejected()->create();

        $this->assertSame(1, User::approved()->count());
        $this->assertSame(1, User::awaitingApproval()->count());
    }
}
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --filter=PlayerApprovalTest`
Expected: FAIL — `approval_status` does not exist and `isApproved()` is undefined.

- [ ] **Step 3: Write the migration**

Run `php artisan make:migration add_approval_to_users_table`, then replace its body:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Admission to the league, as distinct from admission to the website.
 *
 * An enum rather than the two nullable timestamps that would mirror
 * email_verified_at: three states expressed with two nullable columns admit a
 * fourth combination, both-set, that means nothing. An enum cannot express a
 * state that does not exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('approval_status')->default('pending')->index()->after('is_admin');
            $table->timestamp('approval_decided_at')->nullable()->after('approval_status');
            $table->foreignId('approval_decided_by')->nullable()->after('approval_decided_at');
        });

        // Grandfather every account that predates the requirement. They
        // registered when no approval step existed and cannot be expected to
        // have satisfied one -- the same reasoning, and the same shape, as the
        // email-verification backfill.
        //
        // approval_decided_by stays null on purpose: no person made this
        // decision, and naming one would be a false audit trail.
        DB::table('users')->update([
            'approval_status' => 'approved',
            'approval_decided_at' => DB::raw('created_at'),
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['approval_status', 'approval_decided_at', 'approval_decided_by']);
        });
    }
};
```

**Note on `foreignId` without `constrained()`:** `users.id` is a ULID, not a
bigint. Check what `approval_decided_by` must be before running this — if the
column type mismatches, use `$table->string('approval_decided_by')->nullable()`
instead. Confirm with:

```bash
php artisan tinker --execute='echo \Illuminate\Support\Facades\Schema::getColumnType("users","id");'
```

- [ ] **Step 4: Add the model surface**

In `app/Models/User.php`, add to the `casts()` method:

```php
'approval_decided_at' => 'datetime',
```

and add these methods to the class:

```php
/**
 * The single expression of the approval rule.
 *
 * Every gate and every view calls this rather than comparing the column, so
 * the button that offers an action and the guard that refuses it can never
 * disagree about what "approved" means.
 */
public function isApproved(): bool
{
    return $this->approval_status === 'approved';
}

public function isPendingApproval(): bool
{
    return $this->approval_status === 'pending';
}

public function scopeApproved($query)
{
    return $query->where('approval_status', 'approved');
}

public function scopeAwaitingApproval($query)
{
    return $query->where('approval_status', 'pending');
}
```

Add `'approval_status'` to `$fillable` **only if** the admin creation path in
Task 5 sets it via mass assignment; otherwise leave it out and set it
explicitly. Prefer leaving it out — an approval state that can be mass-assigned
from request data is a privilege-escalation shape.

- [ ] **Step 5: Update the factory**

In `database/factories/UserFactory.php`, add to `definition()`:

```php
// Approved by default, exactly as email_verified_at is set by default: 16
// test files create users and then hit routes this plan gates, and a factory
// that produced pending users would break all of them at once for reasons
// unrelated to what they test.
'approval_status' => 'approved',
'approval_decided_at' => now(),
```

and add two states:

```php
public function pending(): static
{
    return $this->state(fn (array $attributes) => [
        'approval_status' => 'pending',
        'approval_decided_at' => null,
    ]);
}

public function rejected(): static
{
    return $this->state(fn (array $attributes) => [
        'approval_status' => 'rejected',
        'approval_decided_at' => now(),
    ]);
}
```

- [ ] **Step 6: Migrate and run the tests**

```bash
php artisan migrate
php artisan test --filter=PlayerApprovalTest
php artisan test
```

Expected: `PlayerApprovalTest` passes; the full suite still passes at 139 plus
the four new tests. **If other tests fail here, the factory default is wrong —
fix the factory, not the tests.**

- [ ] **Step 7: Verify the backfill on the real database**

```bash
php artisan tinker --execute='
echo "approved: ".\App\Models\User::approved()->count()." / ".\App\Models\User::count()."\n";
echo "with a decider: ".\App\Models\User::whereNotNull("approval_decided_by")->count()." (must be 0)\n";'
```

Expected: 102 / 102 approved, 0 with a decider.

- [ ] **Step 8: HAND-OFF — do not run git**

```
feat(users): add the approval state

Admission to the league, as distinct from admission to the website. An enum
rather than the two nullable timestamps that would mirror email_verified_at,
because three states from two nullable columns admit a fourth combination
that means nothing.

isApproved() is the single expression of the rule; every gate and view added
after this calls it rather than comparing the column, so the control that
offers an action and the guard that refuses it cannot disagree.

All 102 existing accounts are grandfathered to approved at their own
created_at. approval_decided_by stays null for them: no person made that
decision, and naming one would be a false audit trail.

The factory produces approved users by default, as it already produces
verified ones -- 16 test files create users and then hit routes the next
commit gates.
```

---

### Task 2: The gate

**Files:**
- Modify: `app/Http/Controllers/Poker/PokerTournamentController.php` (the `register` method, from line 114)
- Modify: `app/Http/Controllers/Poker/PokerTournamentRegistrantController.php:29,62` and the `store` method
- Test: `tests/Feature/PlayerApprovalTest.php`

**Interfaces:**
- Consumes: `User::isApproved()`, factory states `pending()` / `rejected()` from Task 1.
- Produces: nothing new; three paths that now refuse unapproved players.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/PlayerApprovalTest.php` (add the imports
`App\Models\PokerSeason`, `App\Models\PokerTournament`, `App\Models\Venue`):

```php
    private function makeTournament(): PokerTournament
    {
        $venue = Venue::create(['name' => 'The Grand Card Room', 'address' => '100 Casino Blvd']);

        $season = PokerSeason::create([
            'name' => 'Season 1',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
            'is_current' => true,
        ]);

        return PokerTournament::create([
            'name' => 'Weekly Freezeout',
            'scheduled_at' => now()->addDay(),
            'start_time' => now()->addDay()->addHours(2),
            'venue_id' => $venue->id,
            'season_id' => $season->id,
        ]);
    }

    public function test_a_pending_player_cannot_self_register(): void
    {
        $player = User::factory()->pending()->create(['is_admin' => false]);
        $tournament = $this->makeTournament();

        $this->actingAs($player)
            ->post(route('tournaments.register', $tournament))
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('tournament_registrants', [
            'tournament_id' => $tournament->id,
            'user_id' => $player->id,
        ]);
    }

    public function test_a_rejected_player_cannot_self_register(): void
    {
        // Rejection is not merely absence from the queue: it must refuse.
        $player = User::factory()->rejected()->create(['is_admin' => false]);
        $tournament = $this->makeTournament();

        $this->actingAs($player)
            ->post(route('tournaments.register', $tournament))
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('tournament_registrants', [
            'tournament_id' => $tournament->id,
            'user_id' => $player->id,
        ]);
    }

    public function test_an_approved_player_can_self_register(): void
    {
        $player = User::factory()->create(['is_admin' => false]);
        $tournament = $this->makeTournament();

        $this->actingAs($player)->post(route('tournaments.register', $tournament));

        $this->assertDatabaseHas('tournament_registrants', [
            'tournament_id' => $tournament->id,
            'user_id' => $player->id,
        ]);
    }

    public function test_an_admin_cannot_register_a_pending_player_via_the_override(): void
    {
        // The same controller method serves self-registration and the admin
        // user_id override. The gate must read the TARGET user, not the actor,
        // or an administrator becomes a way around the rule.
        $admin = User::factory()->create(['is_admin' => true]);
        $player = User::factory()->pending()->create(['is_admin' => false]);
        $tournament = $this->makeTournament();

        $this->actingAs($admin)->post(
            route('tournaments.register', $tournament),
            ['user_id' => $player->id]
        )->assertSessionHas('error');

        $this->assertDatabaseMissing('tournament_registrants', [
            'tournament_id' => $tournament->id,
            'user_id' => $player->id,
        ]);
    }

    public function test_an_admin_cannot_register_a_pending_player_via_the_registrant_form(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $player = User::factory()->pending()->create(['is_admin' => false]);
        $tournament = $this->makeTournament();

        $this->actingAs($admin)->post(route('poker.registrants.store'), [
            'tournament_id' => $tournament->id,
            'user_id' => $player->id,
            'player_name' => 'Ada Lovelace',
            'registered_at' => now()->format('Y-m-d H:i:s'),
        ])->assertSessionHasErrors('user_id');

        $this->assertDatabaseMissing('tournament_registrants', [
            'tournament_id' => $tournament->id,
            'user_id' => $player->id,
        ]);
    }

    public function test_the_registrant_picker_offers_only_approved_players(): void
    {
        // A form that offers a choice its own store would refuse is a worse
        // failure than one that never offers it.
        $admin = User::factory()->create(['is_admin' => true]);
        $approved = User::factory()->create(['first_name' => 'Approvedy', 'is_admin' => false]);
        $pending = User::factory()->pending()->create(['first_name' => 'Pendingly', 'is_admin' => false]);

        $this->actingAs($admin)->get(route('poker.registrants.create'))
            ->assertOk()
            ->assertSee('Approvedy')
            ->assertDontSee('Pendingly');
    }
```

- [ ] **Step 2: Run them and watch them fail**

Run: `php artisan test --filter=PlayerApprovalTest`
Expected: the six new tests FAIL — nothing gates anything yet.

- [ ] **Step 3: Gate the self and admin-override path**

In `PokerTournamentController@register`, immediately after `$user =
\App\Models\User::findOrFail($targetUserId);` — and **before** the
`registrants()->create()` call — insert:

```php
        // The gate reads the TARGET user, not the actor. The same method
        // serves self-registration and the admin user_id override, so checking
        // the actor would make an administrator a way around the rule.
        //
        // The message names WHICH gate refused: a player can now be stopped by
        // approval or by email verification, and "you cannot do this" without a
        // reason turns support into guesswork.
        if (! $user->isApproved()) {
            $errorMsg = ($targetUserId === auth()->id())
                ? 'Your account is waiting for approval by a league administrator, so you cannot enter tournaments yet.'
                : 'That account has not been approved by a league administrator yet.';

            return back()->with('error', $errorMsg);
        }
```

- [ ] **Step 4: Gate the admin registrant path**

In `PokerTournamentRegistrantController@store`, change the `user_id` rule:

```php
            'user_id' => [
                'required',
                'exists:users,id',
                // Approval is a validation concern here rather than an abort:
                // this arrives from a form, and a field-level error puts the
                // message beside the field that caused it.
                \Illuminate\Validation\Rule::exists('users', 'id')->where('approval_status', 'approved'),
            ],
```

- [ ] **Step 5: Filter both pickers**

In the same controller, at lines 29 and 62, change `$users = User::all();` to:

```php
        // Approved only. The store above refuses anyone else, and a picker that
        // offers a choice the store will reject is a worse failure than one
        // that never offers it.
        $users = User::approved()->orderBy('first_name')->get();
```

- [ ] **Step 6: Run the tests**

```bash
php artisan test --filter=PlayerApprovalTest
php artisan test
```

Expected: all pass.

- [ ] **Step 7: Prove each guard bites**

A guard nobody has watched fail is an assumption. For **each** of the three,
remove it, confirm the matching test goes red, and restore it:

1. delete the `isApproved()` block from `register` → `test_a_pending_player_cannot_self_register` fails
2. drop the `Rule::exists` clause → `test_an_admin_cannot_register_a_pending_player_via_the_registrant_form` fails
3. revert the picker to `User::all()` → `test_the_registrant_picker_offers_only_approved_players` fails

- [ ] **Step 8: HAND-OFF — do not run git**

```
feat(tournaments): refuse entry to unapproved players

Gates all three paths that can create a registration, because the rule is
that a player cannot register OR be registered: self-service, the admin
user_id override that shares the same controller method, and the admin
registrant form.

The gate reads the target user rather than the actor. The override path runs
as an administrator, so checking the actor would have made an administrator
a way around the rule rather than a user of it.

The registrant picker now offers approved accounts only. A form that offers
a choice its own store would refuse is a worse failure than one that never
offers it.

Refusal messages name which gate refused. A player can now be blocked by
approval or by email verification, and an unexplained refusal turns support
into guesswork.
```

---

### Task 3: The pending player's view

**Files:**
- Modify: `resources/views/events.blade.php` (the register control, around line 98)
- Modify: `resources/views/poker/tournaments/show.blade.php` (the register control)
- Test: `tests/Feature/PlayerApprovalTest.php`

**Interfaces:**
- Consumes: `User::isApproved()` from Task 1.

- [ ] **Step 1: Write the failing test**

```php
    public function test_a_pending_player_is_told_why_rather_than_shown_a_register_button(): void
    {
        $player = User::factory()->pending()->create(['is_admin' => false]);
        $this->makeTournament();

        $response = $this->actingAs($player)->get('/events');

        $response->assertOk()
            ->assertSee('Awaiting approval')
            ->assertDontSee('Register for this tournament');
    }
```

**Before writing the view, check the register button's exact accessible label**
in `resources/views/events.blade.php` around line 99 and use that string in
`assertDontSee`. If the button reads simply "Register", assert on the form
action instead — `route('tournaments.register', $tournament)` — because the word
"Register" appears elsewhere on the page and a loose assertion would pass for
the wrong reason.

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --filter=test_a_pending_player_is_told_why`
Expected: FAIL — the button still renders.

- [ ] **Step 3: Change the control in `events.blade.php`**

The existing branch is:

```blade
@if ($tournament->viewer_registered ?? false)
    <x-badge variant="open">{{ __("You're registered") }}</x-badge>
@elseif ($tournament->registration_open)
    <form action="{{ route('tournaments.register', $tournament) }}" method="POST">
```

Insert a branch **before** the `registration_open` case, so a pending player
never sees a control that would fail:

```blade
@elseif (auth()->check() && ! auth()->user()->isApproved())
    {{-- Not hidden, explained. A control that vanishes with no reason reads
         as a bug; the guard in PokerTournamentController refuses this same
         case, and both read isApproved() so they cannot disagree. --}}
    <x-badge>{{ __('Awaiting approval') }}</x-badge>
```

- [ ] **Step 4: Make the same change in `poker/tournaments/show.blade.php`**

Find the equivalent register control and add the identical branch. Search for it
with `grep -n "tournaments.register" resources/views/poker/tournaments/show.blade.php`.

- [ ] **Step 5: Run the tests**

```bash
php artisan test
```

- [ ] **Step 6: Look at it**

```bash
php artisan serve --port=8899
```

Screenshot `/events` as a pending player, in both themes. Snap-confined
Chromium cannot write to `/tmp` or any dot-directory — use `$HOME/ftap-shots`.
Confirm the badge reads as a state and not as an error.

- [ ] **Step 7: HAND-OFF — do not run git**

```
feat(events): explain the approval gate instead of hiding the control

A pending player sees "Awaiting approval" where the register button would
be, rather than a gap. A control that vanishes with no reason reads as a
bug, and the player has no way to learn that a decision is pending on them.

The view and the controller guard both call isApproved(), so the control
that is offered and the guard that refuses cannot disagree.
```

---

### Task 4: The pending queue

**Files:**
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/UserController.php` (`index`, plus `approve` and `reject`)
- Modify: `resources/views/users/index.blade.php`
- Modify: `resources/views/users/show.blade.php`
- Test: `tests/Feature/UserManagementTest.php`

**Interfaces:**
- Consumes: `User::awaitingApproval()`, `User::isApproved()` from Task 1.
- Produces: routes `users.approve` (PATCH `users/{user}/approve`) and `users.reject` (PATCH `users/{user}/reject`).

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/UserManagementTest.php`:

```php
    public function test_the_pending_queue_lists_accounts_awaiting_a_decision(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        User::factory()->pending()->create(['first_name' => 'Pendingly']);

        $this->actingAs($admin)->get(route('users.index'))
            ->assertOk()
            ->assertSee('Pendingly')
            ->assertSee('Awaiting approval');
    }

    public function test_the_queue_is_absent_when_nothing_is_pending(): void
    {
        // Absent, not empty-stated: a heading over nothing is furniture.
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('users.index'))
            ->assertOk()
            ->assertDontSee('Awaiting approval');
    }

    public function test_approving_records_who_decided_and_when(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $player = User::factory()->pending()->create();

        $this->actingAs($admin)->patch(route('users.approve', $player))
            ->assertRedirect();

        $player->refresh();
        $this->assertTrue($player->isApproved());
        $this->assertNotNull($player->approval_decided_at);
        $this->assertSame($admin->id, $player->approval_decided_by);
    }

    public function test_rejecting_keeps_the_account_and_records_the_decision(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $player = User::factory()->pending()->create();

        $this->actingAs($admin)->patch(route('users.reject', $player));

        $player->refresh();
        $this->assertSame('rejected', $player->approval_status);
        $this->assertSame($admin->id, $player->approval_decided_by);
        $this->assertDatabaseHas('users', ['id' => $player->id]);
    }

    public function test_a_rejected_account_can_be_approved_from_the_detail_page(): void
    {
        // The only route back once it has left the queue. Without this,
        // "reversible" is a claim the UI does not support.
        $admin = User::factory()->create(['is_admin' => true]);
        $player = User::factory()->rejected()->create();

        $this->actingAs($admin)->patch(route('users.approve', $player));

        $this->assertTrue($player->fresh()->isApproved());
    }

    public function test_a_player_cannot_approve_anyone(): void
    {
        $player = User::factory()->create(['is_admin' => false]);
        $other = User::factory()->pending()->create();

        $this->actingAs($player)->patch(route('users.approve', $other))->assertForbidden();

        $this->assertFalse($other->fresh()->isApproved());
    }
```

- [ ] **Step 2: Run them and watch them fail**

Run: `php artisan test --filter=UserManagementTest`
Expected: FAIL — the routes do not exist.

- [ ] **Step 3: Add the routes**

In `routes/web.php`, inside the existing admin-only group that already carries
the `users` resource (find it with `grep -n "users" routes/web.php`), add:

```php
    Route::patch('users/{user}/approve', [UserController::class, 'approve'])->name('users.approve');
    Route::patch('users/{user}/reject', [UserController::class, 'reject'])->name('users.reject');
```

They must sit inside the group guarded by `EnsureUserIsAdmin`, which is what
makes `test_a_player_cannot_approve_anyone` pass without a separate check.
**Verify that placement** — if the resource is registered outside the admin
group, add the middleware explicitly rather than assuming.

- [ ] **Step 4: Add the controller methods**

In `app/Http/Controllers/UserController.php`:

```php
    /**
     * Admit a player to the league.
     *
     * Also the route back for a REJECTED account: once rejected it has left
     * the pending queue, so the detail page is the only place the decision can
     * be reversed.
     */
    public function approve(User $user): RedirectResponse
    {
        $user->update([
            'approval_status' => 'approved',
            'approval_decided_at' => now(),
            'approval_decided_by' => auth()->id(),
        ]);

        return back()->with('status', $user->first_name.' '.$user->last_name.' can now enter tournaments.');
    }

    /**
     * Refuse a player, keeping the account.
     *
     * Deleting would make the decision unrecoverable and let the same person
     * re-register immediately with no trace of the refusal.
     */
    public function reject(User $user): RedirectResponse
    {
        $user->update([
            'approval_status' => 'rejected',
            'approval_decided_at' => now(),
            'approval_decided_by' => auth()->id(),
        ]);

        return back()->with('status', $user->first_name.' '.$user->last_name.' was not approved.');
    }
```

Add `use Illuminate\Http\RedirectResponse;` and `use App\Models\User;` if absent.

- [ ] **Step 5: Pass the queue to the view**

In `UserController@index`, alongside the existing
`$users = User::orderBy('first_name')->paginate(15);`:

```php
        // Not paginated: a queue that needs paging is a queue nobody is
        // working. If it ever grows that large, that is the signal, not a
        // pagination bug.
        $pending = User::awaitingApproval()->orderBy('created_at')->get();

        return view('users.index', compact('users', 'pending'));
```

- [ ] **Step 6: Render the queue**

In `resources/views/users/index.blade.php`, immediately after the
`session('status')` alert and **before** the main `<x-card flush>`:

```blade
        {{-- Rendered only when non-empty. A heading over an empty table is
             furniture: it takes attention every visit and says nothing. --}}
        @if ($pending->isNotEmpty())
            <x-card :title="__('Awaiting approval')">
                <x-table>
                    <x-slot name="head">
                        <th scope="col">{{ __('Name') }}</th>
                        <th scope="col">{{ __('Email') }}</th>
                        <th scope="col">{{ __('Registered') }}</th>
                        <th scope="col" class="table__actions">{{ __('Actions') }}</th>
                    </x-slot>

                    @foreach ($pending as $candidate)
                        <tr>
                            <td>{{ $candidate->first_name }} {{ $candidate->last_name }}</td>
                            <td>{{ $candidate->email }}</td>
                            <td>{{ $candidate->created_at?->format('M d, Y') ?? '—' }}</td>
                            <td class="table__actions">
                                <div class="l-cluster l-cluster--end">
                                    <a class="link" href="{{ route('users.show', $candidate) }}">{{ __('View') }}</a>

                                    <form action="{{ route('users.approve', $candidate) }}" method="POST">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="link">{{ __('Approve') }}</button>
                                    </form>

                                    <form action="{{ route('users.reject', $candidate) }}" method="POST"
                                          data-confirm="{{ __('Reject :name? They keep their account but cannot enter tournaments.', ['name' => $candidate->first_name.' '.$candidate->last_name]) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="link link--danger">{{ __('Reject') }}</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-table>
            </x-card>
        @endif
```

- [ ] **Step 7: Add the approval column to the main table**

This is what makes rejection reversible in fact. In the head slot, after
`Role`:

```blade
                    <th scope="col">{{ __('Approval') }}</th>
```

and in the row, after the role cell:

```blade
                        <td>
                            @if ($user->isApproved())
                                <x-badge variant="open">{{ __('Approved') }}</x-badge>
                            @elseif ($user->isPendingApproval())
                                <x-badge>{{ __('Pending') }}</x-badge>
                            @else
                                <x-badge variant="primary">{{ __('Rejected') }}</x-badge>
                            @endif
                        </td>
```

**The empty-state `colspan` must go from 6 to 7.** It is the last `<td>` in the
`@empty` branch and will silently misalign if missed.

- [ ] **Step 8: Add the controls to `users.show`**

Add a section offering Approve when the account is not approved, and Reject when
it is. Match the surrounding markup of `resources/views/users/show.blade.php`;
both are the same `<form>` + `@method('PATCH')` shape as Step 6.

- [ ] **Step 9: Run the tests, then look at the page**

```bash
php artisan test
```

Then screenshot `/users` as an admin with at least one pending account, in both
themes.

- [ ] **Step 10: HAND-OFF — do not run git**

```
feat(users): add the approval queue

Accounts awaiting a decision appear above the user table with Approve,
Reject and View, and only when there are any -- a heading over an empty
table is furniture that costs attention on every visit and says nothing.

The main table gains an approval column. That is what makes rejection
reversible in fact rather than in principle: a rejected account has left the
queue, so without a status on the main list there is no route back to it.
Both decisions record who made them and when.

Rejecting keeps the account. Deleting would make the decision unrecoverable
and let the same person re-register immediately with no trace of the
refusal.
```

---

### Task 5: Register Player

**Files:**
- Modify: `routes/web.php` (restore `users.create` and `users.store`)
- Modify: `app/Http/Controllers/UserController.php` (`create`, `store`)
- Create: `resources/views/users/create.blade.php`
- Modify: `resources/views/users/index.blade.php` (the header button)
- Test: `tests/Feature/UserManagementTest.php`

**Interfaces:**
- Consumes: everything from Task 1.
- Produces: routes `users.create` (GET `users/create`), `users.store` (POST `users`).

- [ ] **Step 1: Write the failing tests**

```php
    public function test_an_admin_can_register_a_player_who_is_approved_immediately(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('users.store'), [
            'first_name' => 'Dana',
            'last_name' => 'Whitlock',
            'email' => 'dana@example.com',
        ])->assertRedirect();

        $player = User::where('email', 'dana@example.com')->first();

        $this->assertNotNull($player);
        $this->assertTrue($player->isApproved());
        $this->assertSame($admin->id, $player->approval_decided_by);

        // Approval only. The owner chose that an admin-created player still
        // confirms their own address.
        $this->assertFalse($player->hasVerifiedEmail());
    }

    public function test_an_admin_created_player_is_sent_an_invite_to_set_a_password(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('users.store'), [
            'first_name' => 'Dana',
            'last_name' => 'Whitlock',
            'email' => 'dana@example.com',
        ]);

        $player = User::where('email', 'dana@example.com')->first();

        Notification::assertSentTo($player, ResetPassword::class);
    }

    public function test_the_invite_link_is_shown_to_the_admin_as_a_fallback(): void
    {
        // MAIL_MAILER is log. Without this the button produces an account
        // nobody can get into.
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('users.store'), [
            'first_name' => 'Dana',
            'last_name' => 'Whitlock',
            'email' => 'dana@example.com',
        ])->assertSessionHas('invite_url');
    }

    public function test_a_player_cannot_register_a_player(): void
    {
        $player = User::factory()->create(['is_admin' => false]);

        $this->actingAs($player)->get(route('users.create'))->assertForbidden();
    }
```

Add `use Illuminate\Auth\Notifications\ResetPassword;` and
`use Illuminate\Support\Facades\Notification;`.

- [ ] **Step 2: Run them and watch them fail**

Expected: FAIL — `users.create` and `users.store` do not exist. They were
removed in Phase 0 as dead routes whose controller methods were missing.

- [ ] **Step 3: Restore the routes**

Find the `Route::resource('users', ...)` call and its `->except([...])` list.
Remove `'create'` and `'store'` from that list rather than adding separate
routes, so the resource stays the single declaration.

- [ ] **Step 4: Add the controller methods**

```php
    public function create(): View
    {
        return view('users.create');
    }

    /**
     * Register a player directly. Approved on the spot -- an administrator
     * creating the account is the decision.
     *
     * No password field. The account gets an unusable random one and the
     * player sets their own through a password-reset link, which is a signed,
     * expiring, single-use token the framework already issues. Inventing a
     * parallel invite-token system would mean a second table, a second expiry
     * policy and a second set of security assumptions to do the same job.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'nickname' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'is_admin' => ['nullable', 'boolean'],
        ]);

        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'nickname' => $validated['nickname'] ?? null,
            'email' => $validated['email'],
            'password' => Hash::make(Str::random(40)),
            'is_admin' => (bool) ($validated['is_admin'] ?? false),
        ]);

        $user->forceFill([
            'approval_status' => 'approved',
            'approval_decided_at' => now(),
            'approval_decided_by' => auth()->id(),
        ])->save();

        // Verification is NOT granted here: admin creation confers approval
        // only, by the owner's decision.
        event(new Registered($user));

        Password::sendResetLink(['email' => $user->email]);

        // The same token, surfaced for the administrator to pass on by hand.
        // MAIL_MAILER is log, so without this the button produces an account
        // nobody can get into. It stays useful afterwards as a fallback when a
        // player says the mail never arrived.
        $inviteUrl = route('password.reset', [
            'token' => Password::createToken($user),
            'email' => $user->email,
        ]);

        return redirect()->route('users.index')
            ->with('status', $user->first_name.' '.$user->last_name.' was registered and approved.')
            ->with('invite_url', $inviteUrl);
    }
```

Add imports: `Illuminate\Auth\Events\Registered`,
`Illuminate\Support\Facades\Hash`, `Illuminate\Support\Facades\Password`,
`Illuminate\Support\Str`, `Illuminate\View\View`.

**Note:** `Password::sendResetLink` and `Password::createToken` each issue a
token, and issuing a second invalidates the first in some driver
configurations. Verify with the test in Step 6 that the surfaced link actually
works; if it does not, drop `sendResetLink` and send the notification manually
with the token from `createToken` so only one exists.

- [ ] **Step 5: Build the form and the button**

Create `resources/views/users/create.blade.php` modelled on
`resources/views/poker/seasons/create.blade.php` — same `<x-card>`, `<x-field>`,
`<x-btn>` vocabulary. Fields: first name, last name, nickname, email, and an
admin checkbox. **No password field.**

In `resources/views/users/index.blade.php`, add the header action, matching
`poker/seasons/index.blade.php`:

```blade
        <x-page-header :eyebrow="__('Setup')" :title="__('User Management')">
            <x-slot name="actions">
                <x-btn variant="primary" :href="route('users.create')">{{ __('Register Player') }}</x-btn>
            </x-slot>
        </x-page-header>
```

Render the invite link on the index page when present:

```blade
        @if (session('invite_url'))
            <x-alert variant="info">
                {{ __('Send this link to the player so they can set a password:') }}
                <br>
                <span class="u-mono">{{ session('invite_url') }}</span>
            </x-alert>
        @endif
```

**Check `u-mono` exists** before using it — `grep -n "u-mono" resources/css/`.
If it does not, use no class rather than inventing one; `ModifierClassGuardTest`
does not cover utilities, so a wrong name would fail silently.

- [ ] **Step 6: Run the tests and follow the link by hand**

```bash
php artisan test
php artisan serve --port=8899
```

Register a player through the UI, copy the surfaced link, open it, set a
password, and log in as that player. Confirm they are approved and land on
`/verify-email`, not the dashboard.

- [ ] **Step 7: HAND-OFF — do not run git**

```
feat(users): let an admin register a player

Restores users.create and users.store, removed in Phase 0 as dead routes
whose controller methods were missing. A player created this way is approved
on the spot -- an administrator creating the account is the decision -- and
records who made it.

There is no password field. The account gets an unusable random password and
the player sets their own through a password-reset link: a signed, expiring,
single-use token the framework already issues, with the whole flow already
in the app. A parallel invite-token system would mean a second table, a
second expiry policy and a second set of security assumptions for the same
job.

The link is also surfaced to the administrator. MAIL_MAILER is log, so
without that the button produces an account nobody can get into; it stays
useful afterwards when a player says the mail never arrived.

Admin creation confers approval only. The player still verifies their own
address.
```

---

### Task 6: The link actions on `users.show`

**Files:**
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/UserController.php` (`sendInvite`, `sendVerification`)
- Modify: `resources/views/users/show.blade.php`
- Test: `tests/Feature/UserManagementTest.php`

**Interfaces:**
- Produces: routes `users.invite` (POST `users/{user}/invite`), `users.verification` (POST `users/{user}/verification`).

- [ ] **Step 1: Write the failing tests**

```php
    public function test_an_admin_can_resend_a_verification_link(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['is_admin' => true]);
        $player = User::factory()->unverified()->create();

        $this->actingAs($admin)->post(route('users.verification', $player))
            ->assertRedirect()
            ->assertSessionHas('verification_url');

        Notification::assertSentTo($player, VerifyEmail::class);
    }

    public function test_resending_verification_is_refused_for_a_verified_account(): void
    {
        // An action that cannot do anything should not pretend to.
        $admin = User::factory()->create(['is_admin' => true]);
        $player = User::factory()->create();

        $this->actingAs($admin)->post(route('users.verification', $player))
            ->assertSessionHas('error');
    }

    public function test_an_admin_can_resend_an_invite_link(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['is_admin' => true]);
        $player = User::factory()->create();

        $this->actingAs($admin)->post(route('users.invite', $player))
            ->assertSessionHas('invite_url');

        Notification::assertSentTo($player, ResetPassword::class);
    }

    public function test_a_player_cannot_send_links_for_anyone(): void
    {
        $player = User::factory()->create(['is_admin' => false]);
        $other = User::factory()->unverified()->create();

        $this->actingAs($player)->post(route('users.verification', $other))->assertForbidden();
    }
```

Add `use Illuminate\Auth\Notifications\VerifyEmail;`.

- [ ] **Step 2: Run them and watch them fail**

- [ ] **Step 3: Add the routes**

Inside the same admin-only group as Task 4:

```php
    Route::post('users/{user}/invite', [UserController::class, 'sendInvite'])->name('users.invite');
    Route::post('users/{user}/verification', [UserController::class, 'sendVerification'])->name('users.verification');
```

- [ ] **Step 4: Add the controller methods**

```php
    /**
     * Re-issue the password-set link for a player.
     */
    public function sendInvite(User $user): RedirectResponse
    {
        Password::sendResetLink(['email' => $user->email]);

        return back()
            ->with('status', 'A password link was sent to '.$user->email.'.')
            ->with('invite_url', route('password.reset', [
                'token' => Password::createToken($user),
                'email' => $user->email,
            ]));
    }

    /**
     * Re-issue the email verification link for a player.
     *
     * EmailVerificationNotificationController cannot serve this: it acts on
     * $request->user(), the authenticated actor, and an administrator acting
     * on someone else's account is a different operation.
     */
    public function sendVerification(User $user): RedirectResponse
    {
        if ($user->hasVerifiedEmail()) {
            return back()->with('error', $user->email.' is already verified.');
        }

        $user->sendEmailVerificationNotification();

        return back()
            ->with('status', 'A verification link was sent to '.$user->email.'.')
            ->with('verification_url', URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes(config('auth.verification.expire', 60)),
                ['id' => $user->id, 'hash' => sha1($user->email)]
            ));
    }
```

Add `use Illuminate\Support\Facades\URL;`.

- [ ] **Step 5: Add the controls to `users.show`**

Render each only when it can act — the invite while the account has never set a
password is not knowable from the schema, so offer it always; the verification
action only when `! $user->hasVerifiedEmail()`. Surface both returned URLs the
same way Task 5 surfaces `invite_url`.

- [ ] **Step 6: Run everything and follow both links by hand**

```bash
php artisan test
```

Then, for a real unverified account, copy the surfaced verification URL, open
it, and confirm the account becomes verified.

- [ ] **Step 7: HAND-OFF — do not run git**

```
feat(users): let an admin re-issue invite and verification links

Both are surfaced as copyable URLs as well as sent, for the same reason the
registration flow surfaces the invite: MAIL_MAILER is log, so a link that is
only emailed reaches nobody. They stay useful once a mailer exists, for when
a player says the mail never arrived.

EmailVerificationNotificationController could not serve the second: it acts
on $request->user(), the authenticated actor, and an administrator acting on
another account is a different operation.

Resending verification for an already-verified account is refused rather
than silently doing nothing.
```

---

### Task 7: Audit

- [ ] **Step 1: Full suite and build**

```bash
php artisan test
npm run build
```

- [ ] **Step 2: Walk both gates as a real user**

With the server running: register a new account through `/register`, confirm it
lands pending, confirm `/events` shows "Awaiting approval" and that POSTing to
`tournaments.register` is refused. Approve it from `/users`. Confirm the badge
and the button both change.

- [ ] **Step 3: Confirm the two gates report themselves distinctly**

A player blocked by approval and a player blocked by verification must see
different messages. Check both, and check a player blocked by *both* sees
something coherent rather than two contradictory notices.

- [ ] **Step 4: Guard tests**

```bash
php artisan test --filter="ConvertedViewsTest|ModifierClassGuardTest|PublicRegisterTest|InlineStyleGuardTest|TokenContrastTest|ContentPreservationTest"
```

All must pass. The new views are design-system markup and `ConvertedViewsTest`
will fail on any Tailwind class that creeps in.

- [ ] **Step 5: Screenshot the new surfaces in both themes**

`/users` with a pending account, `/users/create`, and a `users.show` page.
Use `$HOME/ftap-shots` — snap Chromium cannot write to `/tmp`.

- [ ] **Step 6: HAND-OFF — do not run git**

```
docs: record the player-approval audit
```

## Out of scope

- Notifying a player that they were approved or rejected.
- Any change to who can log in. Approval gates tournament entry only.
- The four items still open in `docs/RED-BLACK-EXIT-AUDIT.md`.

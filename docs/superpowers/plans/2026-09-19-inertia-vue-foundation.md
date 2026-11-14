# Inertia + Vue Conversion — Plan 1: Foundation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stand up Inertia + Vue 3 in TypeScript alongside the existing Blade
app, with both drift guards in place, and prove the stack end to end by
converting `/profile`.

**Architecture:** Inertia and Blade coexist in one Laravel application. The 29
routes behind `auth` become Vue pages; the 18 public routes keep server-rendered
Blade. This plan delivers the foundation and exactly one converted page. Nothing
here changes controller logic, models, migrations, or any public page.

**Tech Stack:** Laravel 13.32, PHP 8.5, Inertia.js (`inertiajs/inertia-laravel`
v3.3, `@inertiajs/vue3` v3.7), Vue 3.5 `<script setup lang="ts">`, TypeScript
5.9 strict, Vite 7, PHPUnit 12.

Versions checked against the registries on 2026-09-19, not assumed. An earlier
draft of this plan said `^2.0` for both Inertia packages, which is two majors
stale on the npm side and one on the Composer side.

**Spec:** `docs/superpowers/specs/2026-09-19-inertia-vue-conversion-design.md`

## Global Constraints

- **Never run git commands.** The owner commits by hand. Every "Commit" step is
  a hand-off: state the files and the message, then stop.
- **Run the suite with `bin/suite.sh`**, never a bare `php artisan test` — PHP
  8.5.4 segfaults on a full run on this machine. It prints `ran N of N` and
  exits non-zero on any failure. Do not pipe it; capture with
  `out=$(bin/suite.sh); status=$?`.
- **TypeScript throughout.** Every SFC is `<script setup lang="ts">`. No `.js`
  files added to `resources/js`. No `any` or `@ts-ignore` without a comment
  saying why.
- **The CSS is not rewritten.** Class hooks are copied verbatim from the Blade
  they replace. No scoped styles.
- **No public page changes.** `resources/views/{home,events,contact}.blade.php`,
  `about/`, `rules/`, `auth/` and `layouts/public.blade.php` are out of scope.
- **Venue points stay private**: readable only by their owner and by admins, via
  `VenuePoints::readableBy()`. Inertia props are view data — withhold the figure
  from the props, not in the template.
- **Verify in a browser at 375, 960 and 1440**, in both themes, before any page
  is handed off.

---

## File Structure

**Created:**

| File | Responsibility |
|---|---|
| `resources/views/app.blade.php` | Inertia root document. Head, fonts, theme script, `@vite`, `@inertia`. |
| `resources/js/inertia.ts` | Client bootstrap: `createInertiaApp`, page resolution, layout assignment. |
| `resources/js/types/inertia.d.ts` | `PageProps` — the props every page receives from `HandleInertiaRequests`. |
| `resources/js/Layouts/AppLayout.vue` | Persistent shell: nav, header slot, main, footer, confirm dialog. |
| `resources/js/Layouts/Navigation.vue` | Port of `layouts/navigation.blade.php`. |
| `resources/js/Pages/Profile/Edit.vue` | The proving page. |
| `resources/js/Pages/Profile/Partials/*.vue` | Three profile forms. |
| `app/Http/Middleware/HandleInertiaRequests.php` | Shared props. |
| `tests/Feature/ComponentParityTest.php` | Blade/Vue class-hook parity guard. |
| `tests/Feature/InertiaPropContractTest.php` | Page-prop drift guard. |

**Modified:** `vite.config.js`, `resources/js/app.ts`, `package.json`,
`tsconfig.json`, `bootstrap/app.php`, `app/Http/Controllers/ProfileController.php`,
`.github/workflows/ci.yml`, `resources/js/types/global.d.ts`.

---

## Task 1: Type-checking gate, before any Vue exists

Nothing type-checks today. `package.json` defines only `build` and `dev`; Vite
strips types with esbuild without checking them. A type error ships silently.
This has to be a gate *before* there is TypeScript worth checking, or it never
gets added.

**Files:**
- Modify: `package.json`, `.github/workflows/ci.yml`

**Interfaces:**
- Produces: `npm run typecheck` — exits non-zero on any type error in
  `resources/js/**/*.{ts,vue}`.

- [ ] **Step 1: Install the type checker and the Vue toolchain**

```bash
npm install --save-dev vue@^3.5 @vitejs/plugin-vue@^6.0 vue-tsc@^3.3 \
  @inertiajs/vue3@^3.7
composer require inertiajs/inertia-laravel:^3.3
```

`inertia-laravel` v3.3.4 declares `laravel/framework ^11.35|^12.0|^13.0`, so it
is the version that works here. `@inertiajs/vue3` is at 3.7.1 with a `vue ^3.0`
peer; `@vitejs/plugin-vue` 6.0.9 peers `vite ^5|^6|^7|^8`, satisfied by Vite 7.

- [ ] **Step 2: Add the script**

In `package.json`, add to `scripts`:

```json
"typecheck": "vue-tsc --noEmit"
```

- [ ] **Step 3: Prove it fails on a real type error**

```bash
printf 'const n: number = "not a number";\nexport default n;\n' > resources/js/__typecheck_probe.ts
npm run typecheck; echo "exit=$?"
```

Expected: non-zero exit, naming `__typecheck_probe.ts`. If it exits 0 the gate
is not working — stop and fix before continuing.

- [ ] **Step 4: Remove the probe and confirm clean**

```bash
rm resources/js/__typecheck_probe.ts
npm run typecheck; echo "exit=$?"
```

Expected: exit 0.

- [ ] **Step 5: Wire it into CI**

In `.github/workflows/ci.yml`, in the `test` job, immediately after the
`- name: Build assets` step, add:

```yaml
      # Vite strips types with esbuild without checking them, so a green build
      # says nothing about types. Once per run, not per database leg.
      - name: Type-check
        if: matrix.database == 'sqlite'
        run: npm run typecheck
```

- [ ] **Step 6: Verify the workflow still parses**

```bash
python3 -c "import yaml; yaml.safe_load(open('.github/workflows/ci.yml')); print('ok')"
```

- [ ] **Step 7: Commit (hand-off)**

Files: `package.json`, `package-lock.json`, `composer.json`, `composer.lock`,
`.github/workflows/ci.yml`

```
build: add Inertia, Vue and a type-checking gate

Vite strips types with esbuild without checking them and CI ran only the
build, so a type error shipped silently. npm run typecheck (vue-tsc --noEmit)
now gates CI beside the suite. Verified it fails on a deliberate type error
before being wired in.
```

---

## Task 2: Inertia server side

**Files:**
- Create: `resources/views/app.blade.php`, `app/Http/Middleware/HandleInertiaRequests.php`
- Modify: `bootstrap/app.php`

**Interfaces:**
- Produces: every Inertia response carries shared props
  `auth: { user: { id, first_name, last_name, nickname, is_admin } | null }`
  and `flash: { status: string|null, error: string|null }`.

**The flash trap.** `layouts/app.blade.php` carries a comment explaining that
flash is deliberately *not* rendered at layout level: `ProfileController` flashes
the sentinel `'profile-updated'`, `Auth\PasswordController` flashes
`'password-updated'`, and `Auth\EmailVerificationNotificationController` flashes
`'verification-link-sent'`. Views match those strings rather than display them.
Eight other views render `session('status')` as real copy themselves.

So: the middleware **shares** flash, but `AppLayout.vue` (Task 4) does **not**
render it. Pages opt in, exactly as Blade views do today. The sentinels keep
working unchanged. Layout-level flash is a later plan, after the duplicating
views convert.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/InertiaSharedPropsTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * What every Inertia page is handed, whatever it asks for.
 *
 * Flash is SHARED but not rendered by the layout. Three controllers flash
 * sentinel strings -- 'profile-updated', 'password-updated',
 * 'verification-link-sent' -- that views match on rather than display, so a
 * layout that printed flash generically would show a reader 'profile-updated'.
 * The same reasoning is recorded in layouts/app.blade.php.
 */
class InertiaSharedPropsTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_page_is_given_the_signed_in_user(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Wanda', 'last_name' => 'Reeve', 'approval_status' => 'approved',
        ]);

        $this->actingAs($user)->get(route('profile.edit'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.first_name', 'Wanda')
                ->where('auth.user.is_admin', false)
                ->has('flash')
            );
    }

    public function test_a_sentinel_flash_reaches_the_page_unchanged(): void
    {
        // Not translated, not rendered -- handed over exactly as flashed, so
        // the page can match on it the way the Blade partial does.
        $user = User::factory()->create(['approval_status' => 'approved']);

        $this->actingAs($user)
            ->withSession(['status' => 'profile-updated'])
            ->get(route('profile.edit'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('flash.status', 'profile-updated'));
    }
}
```

- [ ] **Step 2: Run it and watch it fail**

```bash
php artisan test tests/Feature/InertiaSharedPropsTest.php
```

Expected: FAIL — `/profile` still returns a Blade view, so `assertInertia`
reports the response is not an Inertia response.

- [ ] **Step 3: Create the root document**

`resources/views/app.blade.php` — the head is copied from
`layouts/app.blade.php` so fonts, theme and favicon behave identically:

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'First to Act Poker') }}</title>

        <link rel="preload" href="{{ asset('fonts/archivo.woff2') }}" as="font" type="font/woff2" crossorigin>

        {{-- Before Vite, and deliberately inline: it stamps data-theme from
             localStorage before first paint. Moving it into Vue would
             reintroduce the flash of the wrong theme it exists to prevent. --}}
        <x-theme-script />

        @vite(['resources/css/app.css', 'resources/js/app.ts'])
        <link rel="icon" href="{{ asset('favicon.png') }}" type="image/png"/>

        @inertiaHead
    </head>
    <body>
        @inertia
    </body>
</html>
```

- [ ] **Step 4: Create the middleware**

`app/Http/Middleware/HandleInertiaRequests.php`:

```php
<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),

            'auth' => [
                'user' => $user === null ? null : [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'nickname' => $user->nickname,
                    'is_admin' => (bool) $user->is_admin,
                ],
            ],

            // Shared, NOT rendered by the layout. See the class docblock on
            // InertiaSharedPropsTest: three controllers flash sentinel strings
            // that pages match on instead of displaying.
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
```

- [ ] **Step 5: Register the middleware**

In `bootstrap/app.php`, inside `->withMiddleware(function (Middleware $middleware) {`,
append to the `web` group:

```php
    $middleware->web(append: [
        \App\Http\Middleware\HandleInertiaRequests::class,
    ]);
```

- [ ] **Step 6: Point `/profile` at Inertia**

In `app/Http/Controllers/ProfileController.php`, replace the `edit` method:

```php
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'user' => [
                'first_name' => $request->user()->first_name,
                'last_name' => $request->user()->last_name,
                'nickname' => $request->user()->nickname,
                'email' => $request->user()->email,
                'email_verified_at' => $request->user()->email_verified_at?->toIso8601String(),
            ],
        ]);
    }
```

Add `use Inertia\Inertia;` and `use Inertia\Response;` to the imports, and
remove the now-unused `use Illuminate\View\View;` if nothing else in the file
uses it.

- [ ] **Step 7: Run the test again**

```bash
php artisan test tests/Feature/InertiaSharedPropsTest.php
```

Expected: PASS. (`Profile/Edit.vue` does not exist yet — Inertia does not
resolve the component server-side, so the assertions on props still pass.)

- [ ] **Step 8: Commit (hand-off)**

Files: `resources/views/app.blade.php`,
`app/Http/Middleware/HandleInertiaRequests.php`, `bootstrap/app.php`,
`app/Http/Controllers/ProfileController.php`,
`tests/Feature/InertiaSharedPropsTest.php`

```
feat: add the Inertia server side and move /profile onto it

Shares auth.user and flash on every Inertia response. Flash is shared but the
layout does not render it: ProfileController, Auth\PasswordController and
Auth\EmailVerificationNotificationController flash sentinel strings that views
match on rather than display, so a generic flash block would show a reader
'profile-updated'. Pages opt in, as the Blade views do today.
```

---

## Task 3: Vue client bootstrap

**Files:**
- Create: `resources/js/inertia.ts`, `resources/js/types/inertia.d.ts`
- Modify: `vite.config.js`, `resources/js/app.ts`, `resources/js/types/global.d.ts`

**Interfaces:**
- Consumes: `HandleInertiaRequests` shared props from Task 2.
- Produces: `PageProps` type; Vue mounts into `@inertia`; Alpine keeps running
  for Blade pages.

- [ ] **Step 1: Teach Vite about Vue**

`vite.config.js`:

```js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
        }),
        vue(),
    ],
});
```

- [ ] **Step 2: Declare the shared props**

`resources/js/types/inertia.d.ts`:

```ts
export interface AuthUser {
    id: string;
    first_name: string;
    last_name: string;
    nickname: string | null;
    is_admin: boolean;
}

/** What HandleInertiaRequests::share() puts on every page. */
export interface PageProps {
    auth: { user: AuthUser | null };
    flash: { status: string | null; error: string | null };
}
```

- [ ] **Step 3: Write the client bootstrap**

`resources/js/inertia.ts`:

```ts
import { createApp, h, type DefineComponent } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';

/**
 * Mounts Vue, but only on a page Inertia actually rendered.
 *
 * Blade pages ship the same bundle -- one Vite entry serves both halves of the
 * app during the conversion -- and calling createInertiaApp on a page with no
 * #app div throws. The element check is what keeps the public pages working.
 */
export function initInertia(): void {
    if (document.getElementById('app') === null) {
        return;
    }

    void createInertiaApp({
        resolve: (name) => {
            const pages = import.meta.glob<DefineComponent>('./Pages/**/*.vue', { eager: true });
            const page = pages[`./Pages/${name}.vue`];

            if (page === undefined) {
                throw new Error(`Inertia page not found: ${name}`);
            }

            // Task 4 assigns the persistent layout here. Deliberately absent
            // until it exists: importing a file this task does not create
            // would leave `npm run typecheck` red at this checkpoint, and the
            // gate added in Task 1 is only worth having if it stays green.
            return page;
        },
        setup({ el, App, props, plugin }) {
            createApp({ render: () => h(App, props) })
                .use(plugin)
                .mount(el);
        },
    });
}
```

- [ ] **Step 4: Call it from the entry point**

In `resources/js/app.ts`, add the import beside the others and the call at the
end:

```ts
import { initInertia } from './inertia';
```

```ts
// Mounts Vue on Inertia pages and returns immediately on Blade ones, so the
// public half of the app is unaffected -- see inertia.ts.
initInertia();
```

- [ ] **Step 5: Type-check and build**

```bash
npm run typecheck && npx vite build; echo "exit=$?"
```

Expected: exit 0. This task imports nothing it does not create, so the gate
stays green and the checkpoint is safe to commit and to push.

- [ ] **Step 6: Commit (hand-off)**

Files: `vite.config.js`, `resources/js/inertia.ts`,
`resources/js/types/inertia.d.ts`, `resources/js/app.ts`

```
feat: mount Vue on Inertia pages only

One Vite entry serves both halves of the app during the conversion, so the
bundle loads on Blade pages too. initInertia() returns immediately when there
is no #app element, which is what keeps the public pages working.
```

---

## Task 4a: Shared props for the shell

`navigation.blade.php` needs two things the middleware does not yet share, and
both belong in shared props rather than on every page for the same reason a
View Composer supplies them today. `AppServiceProvider::composeNotifications()`
says it outright: "thirty-odd controllers should not each remember to supply it
-- the one that forgot would render a page with no badge and no list, and
nothing would say so."

**Files:**
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`,
  `resources/js/types/inertia.d.ts`, `tests/Feature/InertiaSharedPropsTest.php`

**Interfaces:**
- Produces: `auth.user.display_name: string`; and
  `notifications: { unread_count: number, recent: Array<{ id: string, read_at: string|null, data: { tournament_name: string, place: number, points: number, played_on: string } }> }`.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/InertiaSharedPropsTest.php`:

```php
    public function test_the_shell_is_given_the_display_name_rather_than_deriving_it(): void
    {
        // display_name is an accessor: the nickname when there is one, the
        // first name otherwise. Recomputing that rule in TypeScript would be a
        // second definition of it, free to disagree the first time somebody
        // changes the PHP.
        $user = User::factory()->create([
            'first_name' => 'Wanda', 'nickname' => 'Wan', 'approval_status' => 'approved',
        ]);

        $this->actingAs($user)->get(route('profile.edit'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('auth.user.display_name', 'Wan'));
    }

    public function test_the_shell_is_given_the_notification_badge_and_list(): void
    {
        $user = User::factory()->create(['approval_status' => 'approved']);

        $this->actingAs($user)->get(route('profile.edit'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('notifications.unread_count', 0)
                ->has('notifications.recent', 0)
            );
    }

    public function test_the_recent_list_is_capped_at_ten(): void
    {
        // The panel scrolls. A player with three seasons of history should not
        // have all of it serialised into every page they load -- the same cap
        // the View Composer applies.
        $user = User::factory()->create(['approval_status' => 'approved']);

        for ($i = 0; $i < 12; $i++) {
            $user->notifications()->create([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'type' => 'test',
                'data' => ['tournament_name' => "Night {$i}", 'place' => 1, 'points' => 100, 'played_on' => '2026-09-01'],
            ]);
        }

        $this->actingAs($user)->get(route('profile.edit'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('notifications.recent', 10));
    }

    public function test_a_guest_request_costs_no_notification_queries(): void
    {
        // The composer this replaces says "Guests get nothing. layouts/public
        // renders the same x-topbar and must not pay for a query it has no
        // user for." The middleware runs on the whole web group, so without a
        // guard every public page would run two queries for nobody.
        \Illuminate\Support\Facades\DB::flushQueryLog();
        \Illuminate\Support\Facades\DB::enableQueryLog();

        $this->get(route('home'))->assertOk();

        $queries = \Illuminate\Support\Facades\DB::getQueryLog();
        \Illuminate\Support\Facades\DB::disableQueryLog();

        $touched = collect($queries)->filter(
            fn ($query) => str_contains($query['query'], 'notifications')
        );

        $this->assertCount(0, $touched, 'A signed-out page ran a notification query.');
    }
```

- [ ] **Step 2: Run them and watch them fail**

```bash
php artisan test tests/Feature/InertiaSharedPropsTest.php
```

Expected: the four new tests fail; the four from Task 2 still pass.

- [ ] **Step 3: Share the display name**

In `HandleInertiaRequests::share()`, inside the `auth.user` array, after
`'nickname'`:

```php
                    // The accessor's answer, not the ingredients. Vue must not
                    // re-derive "nickname, or first name" -- that rule lives on
                    // the model.
                    'display_name' => $user->display_name,
```

- [ ] **Step 4: Share the notifications**

Add a third top-level key to the array returned by `share()`:

```php
            /*
             * The shell's notification badge and panel.
             *
             * Shared rather than per-page for the reason
             * AppServiceProvider::composeNotifications() gives about the View
             * Composer it replaces: thirty-odd controllers should not each
             * remember to supply layout data, and the one that forgot would
             * render a page with no badge and nothing to say so.
             *
             * Guests get the empty shape rather than a query. The middleware
             * runs on the whole web group, so without this guard every public
             * page would ask the database about notifications for nobody.
             *
             * Closures: not evaluated unless the response is an Inertia one.
             */
            'notifications' => [
                'unread_count' => fn () => $user?->unreadNotifications()->count() ?? 0,

                // Capped at ten, as the composer caps it. The panel scrolls;
                // three seasons of history should not be serialised into every
                // page load.
                'recent' => fn () => $user === null ? [] : $user->notifications()
                    ->latest()
                    ->limit(10)
                    ->get()
                    ->map(fn ($notification) => [
                        'id' => $notification->id,
                        'read_at' => $notification->read_at?->toIso8601String(),
                        'data' => $notification->data,
                    ])
                    ->all(),
            ],
```

- [ ] **Step 5: Declare the types**

In `resources/js/types/inertia.d.ts`, add `display_name: string;` to `AuthUser`
after `nickname`, and add to `PageProps`:

```ts
    /** What the notification card reads -- see notification-card.blade.php. */
    notifications: {
        unread_count: number;
        recent: Array<{
            id: string;
            read_at: string | null;
            data: {
                tournament_name: string;
                place: number;
                points: number;
                played_on: string;
            };
        }>;
    };
```

- [ ] **Step 6: Run the tests and the type check**

```bash
php artisan test tests/Feature/InertiaSharedPropsTest.php
npm run typecheck
```

Expected: all eight tests pass, typecheck exits 0.

- [ ] **Step 7: Full suite**

```bash
out=$(bin/suite.sh); echo "$out" | tail -1
```

Expected: `0 failing`.

- [ ] **Step 8: Commit (hand-off)**

Files: `app/Http/Middleware/HandleInertiaRequests.php`,
`resources/js/types/inertia.d.ts`, `tests/Feature/InertiaSharedPropsTest.php`

```
feat: share the shell's display name and notifications

navigation.blade.php reads both, and both are layout data rather than page
data -- the same argument the View Composer they replace makes for itself.
Guests get the empty shape without a query, since the middleware runs on the
whole web group.

display_name is shared as the accessor's answer rather than its ingredients,
so Vue does not re-derive a rule the model owns.
```

---

## Task 4b: The seven leaf components

`Navigation.vue` depends on seven Blade components. Each is ported as a Vue
twin, keeping its Blade original for the public pages, and each is added to
`ComponentParityTest::TWINS` (Task 5) as it lands.

**Files:**
- Create, in `resources/js/Components/`: `Topbar.vue`, `Dropdown.vue`,
  `DropdownLink.vue`, `ThemeToggle.vue`, `Monogram.vue`,
  `NotificationCard.vue`, `Btn.vue`

**Port targets and what carries over:**

| Blade | Lines | Note |
|---|---|---|
| `topbar.blade.php` | 41 | Alpine `{ menuOpen: false }` becomes a `ref`. |
| `dropdown.blade.php` | 29 | Alpine `{ open: false }` becomes a `ref`. Keep the `x-on:click.stop` behaviour for any input placed inside — see the note in RESUME-HERE about the filter's search box. |
| `dropdown-link.blade.php` | 6 | Trivial. |
| `theme-toggle.blade.php` | 17 | Calls `toggleTheme()` imported from `theme.ts` directly, not via `data-theme-toggle` — `initTheme()` is not loaded on Inertia pages. |
| `monogram.blade.php` | 51 | Takes a name string; uses `initials()`. Vue needs the initials computed in PHP or ported — prefer passing `display_name` and reusing the existing helper's rule. |
| `notification-card.blade.php` | 66 | Reads `data.tournament_name`, `data.place`, `data.points`, `data.played_on`, and `read_at`. Its two forms post to `notifications.update` and `notifications.destroy`. |
| `btn.blade.php` | 22 | Composes `btn btn--{variant}` plus `btn--sm`. |

- [ ] **Step 1: Port each component, copying every class hook verbatim**

Read each Blade file in full before porting it. The CSS is keyed on these
class names and is not being changed.

- [ ] **Step 2: Nothing to register**

`ComponentParityTest` discovers pairs from the filesystem: any
`Components/X.vue` whose kebab-cased name matches a
`views/components/x.blade.php` is compared automatically. There is no list to
add to and none to forget.

- [ ] **Step 3: Type-check and build**

```bash
npm run typecheck && npx vite build
```

- [ ] **Step 4: Run the parity guard**

```bash
php artisan test tests/Feature/ComponentParityTest.php
```

Expected: PASS. A failure means a port dropped or renamed a hook — fix the
component, not the test.

- [ ] **Step 5: Commit (hand-off)**

```
feat: port the seven components the app shell needs to Vue

Topbar, Dropdown, DropdownLink, ThemeToggle, Monogram, NotificationCard and
Btn. Each keeps its Blade original for the public pages and is registered in
ComponentParityTest, which fails when a pair stops agreeing about the class
hooks the stylesheet is keyed on.
```

---

## Task 4c: Navigation and AppLayout

**Files:**
- Create: `resources/js/Layouts/AppLayout.vue`, `resources/js/Layouts/Navigation.vue`,
  `resources/js/Components/SiteFooter.vue`, `resources/js/Components/ConfirmDialog.vue`
- Modify: `resources/js/inertia.ts`

**Port target.** `resources/views/layouts/app.blade.php` and
`resources/views/layouts/navigation.blade.php` (181 lines). Read both first.

**Behaviours that must survive, each currently in the Blade:**
- `.shell` / `.shell__header` / `.shell__header-inner` / `.shell__content`
  structure, in that nesting. `.shell` is a flex column at `min-height: 100vh`
  with `.shell__content` at `flex: 1`, which holds the footer down on a short
  page.
- **No `.l-container` in the layout.** Every page supplies its own; two of them
  applied the gutter twice, giving 32px a side on a phone.
- The header block renders only when a page provides one.
- `nav-link--current` and `aria-current="page"` on the active item, driven by
  the current URL rather than `route()` — Ziggy is not installed.
- The two distinct action structures: `topbar__actions-desktop` (identity as a
  dropdown) and `topbar__actions-mobile` (a flat row, logout on the left). They
  differ in shape, not just layout, so one markup tree cannot express both.
- The mobile bell menu carries `topbar__bell-menu` and deliberately NOT
  `dropdown--notifications` — both selectors are (0,2,0) and `_dropdown.css`
  loads after `_topbar.css`, so carrying both let the fixed 22rem width win and
  pushed the panel off screen.

- [ ] **Step 1: Write AppLayout.vue**

```vue
<script setup lang="ts">
import Navigation from './Navigation.vue';
import SiteFooter from '../Components/SiteFooter.vue';
import ConfirmDialog from '../Components/ConfirmDialog.vue';
</script>

<template>
    <div class="shell">
        <Navigation />

        <!--
            Flash is shared on every page but deliberately not rendered here.
            Three controllers flash sentinel strings ('profile-updated',
            'password-updated', 'verification-link-sent') that pages match on
            rather than display, and eight Blade views still render their own
            session('status'). The layout takes ownership once those convert.
        -->
        <header v-if="$slots.header" class="shell__header">
            <div class="shell__header-inner"><slot name="header" /></div>
        </header>

        <!--
            No .l-container here. Every page supplies its own; .l-container
            sets padding-inline, so two of them applied the gutter twice --
            32px a side on a phone, where there was least room to give.
        -->
        <main class="shell__content"><slot /></main>

        <SiteFooter />

        <ConfirmDialog />
    </div>
</template>
```

- [ ] **Step 2: Port Navigation.vue**

Use the shared props from Task 4a and the components from Task 4b:

```ts
import { usePage } from '@inertiajs/vue3';
import type { PageProps } from '../types/inertia';

const page = usePage<PageProps>();
const user = page.props.auth.user;

// route() is not available client-side: Ziggy is not installed and is out of
// scope. Current state is decided from the page URL instead.
const isCurrent = (...prefixes: string[]): boolean =>
    prefixes.some((prefix) => page.url.startsWith(prefix));
```

- [ ] **Step 3: Port SiteFooter.vue and ConfirmDialog.vue**

From `resources/views/components/site-footer.blade.php` and
`confirm-dialog.blade.php`, copying class hooks verbatim. Register both in
`ComponentParityTest::TWINS`.

- [ ] **Step 4: Assign the layout**

In `resources/js/inertia.ts`, inside `resolve`, before `return page`:

```ts
            // Every page gets the persistent shell unless it sets its own.
            page.default.layout ??= AppLayout;
```

and add the import at the top:

```ts
import AppLayout from './Layouts/AppLayout.vue';
```

- [ ] **Step 5: Type-check, build, parity guard, full suite**

```bash
npm run typecheck && npx vite build
php artisan test tests/Feature/ComponentParityTest.php
out=$(bin/suite.sh); echo "$out" | tail -1
```

- [ ] **Step 6: Commit (hand-off)**

```
feat: add the persistent Vue app layout and navigation

Ports layouts/app.blade.php and layouts/navigation.blade.php, copying every
class hook unchanged. Keeps the decisions recorded in the Blade: no
.l-container in the layout, no flash block, two distinct action structures
rather than one restyled, and the mobile bell menu without
dropdown--notifications.
```

---

## Task 5: The component parity guard

Written before any twin exists, so it is never retrofitted. Spec §2 asks for it
"before the second component is converted, not after the eleventh", which puts
it ahead of Task 4b rather than after it -- that is the order it was executed
in, and the order it should be read in.

**Files:**
- Create: `tests/Feature/ComponentParityTest.php`

**Interfaces:**
- Produces: a failing suite when a Blade component and its Vue twin disagree
  about CSS class hooks.

- [ ] **Step 1: Write the test**

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * A Blade component and its Vue twin must agree about class hooks.
 *
 * Eleven components are used by both the public Blade pages and the converted
 * Vue pages, so each exists twice. Two definitions of one thing is the defect
 * this project keeps meeting -- PokerSeason::current(), readableBy(), initials()
 * all exist because a second definition drifted.
 *
 * This does not prove the two behave alike. It proves they agree about the
 * hooks the stylesheet is keyed on, which is the drift that silently changes
 * how a page looks.
 */
class ComponentParityTest extends TestCase
{
    /** Blade component => its Vue twin, both relative to resources/. */
    private const TWINS = [
        'views/components/site-footer.blade.php' => 'js/Components/SiteFooter.vue',
        'views/components/confirm-dialog.blade.php' => 'js/Components/ConfirmDialog.vue',
    ];

    public function test_each_twin_pair_uses_the_same_class_hooks(): void
    {
        $drifted = [];

        foreach (self::TWINS as $blade => $vue) {
            $bladePath = resource_path($blade);
            $vuePath = resource_path($vue);

            $this->assertFileExists($bladePath);
            $this->assertFileExists($vuePath);

            $inBlade = $this->classes(file_get_contents($bladePath));
            $inVue = $this->classes(file_get_contents($vuePath));

            $missing = array_diff($inBlade, $inVue);
            $extra = array_diff($inVue, $inBlade);

            if ($missing !== [] || $extra !== []) {
                $drifted[] = sprintf(
                    "%s vs %s\n      only in Blade: %s\n      only in Vue:   %s",
                    $blade, $vue,
                    implode(', ', $missing) ?: '(none)',
                    implode(', ', $extra) ?: '(none)'
                );
            }
        }

        $this->assertSame([], $drifted,
            "A component and its twin disagree about class hooks:\n  ".implode("\n  ", $drifted)."\n");
    }

    /**
     * Every class literal in a class attribute.
     *
     * Blade and Vue both write class="a b" and Vue adds :class bindings, which
     * are read too -- a hook that appears only inside a binding still has to
     * exist on both sides.
     *
     * @return list<string>
     */
    private function classes(string $source): array
    {
        preg_match_all('/(?::class|class)="([^"]*)"/', $source, $matches);

        $classes = [];

        foreach ($matches[1] as $attribute) {
            // Strip Blade echoes and Vue expressions, keeping bare literals.
            $literal = preg_replace('/\{\{.*?\}\}/s', ' ', $attribute);

            foreach (preg_split('/[\s\'{}:,?]+/', (string) $literal, -1, PREG_SPLIT_NO_EMPTY) as $token) {
                if (preg_match('/^[a-z][a-z0-9_-]*$/i', $token)) {
                    $classes[] = $token;
                }
            }
        }

        $classes = array_values(array_unique($classes));
        sort($classes);

        return $classes;
    }
}
```

- [ ] **Step 2: Run it**

```bash
php artisan test tests/Feature/ComponentParityTest.php
```

Expected: PASS for the two pairs created in Task 4. If it fails, the Vue port
dropped or renamed a hook — fix the component, not the test.

- [ ] **Step 3: Prove it can fail**

Add a stray class to `resources/js/Components/SiteFooter.vue`, e.g. change one
`class="site-footer"` to `class="site-footer site-footer--oops"`, then:

```bash
php artisan test tests/Feature/ComponentParityTest.php
```

Expected: FAIL naming `site-footer--oops` as "only in Vue". Revert the change
and confirm it passes again.

- [ ] **Step 4: Commit (hand-off)**

Files: `tests/Feature/ComponentParityTest.php`

```
test: guard Blade/Vue component pairs against class-hook drift

Eleven components are used by both the public Blade pages and the converted
Vue pages, so each will exist twice. This fails when a pair stops agreeing
about the hooks the stylesheet is keyed on. Written before the twins exist so
it is never retrofitted; proved it fails on an added class before relying on it.
```

---

## Task 6: The page-prop drift guard

Spec §5. Page props are defined in PHP and consumed as TypeScript interfaces —
two definitions of one thing again.

**Files:**
- Create: `tests/Feature/InertiaPropContractTest.php`

**Interfaces:**
- Consumes: every `resources/js/Pages/**/*.vue` that declares
  `interface Props { ... }`.
- Produces: a failing suite when a controller's top-level prop keys stop
  matching the page's declared interface.

- [ ] **Step 1: Write the test**

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A page's Props interface describes what its controller actually sends.
 *
 * The props are built in PHP and consumed in TypeScript, so the interface is a
 * second definition of one thing and free to drift. TypeScript cannot catch it:
 * it checks the Vue side is internally consistent and has no idea what the
 * server sends.
 *
 * Deliberately shallow -- top-level keys only. It catches the common failure, a
 * prop renamed, added or dropped, without reimplementing a type checker in PHP.
 */
class InertiaPropContractTest extends TestCase
{
    use RefreshDatabase;

    /** Inertia component name => the route that renders it. */
    private const PAGES = [
        'Profile/Edit' => 'profile.edit',
    ];

    public function test_each_page_declares_exactly_the_props_it_is_sent(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'approval_status' => 'approved']);
        $mismatches = [];

        foreach (self::PAGES as $component => $routeName) {
            $response = $this->actingAs($admin)->get(route($routeName));
            $response->assertOk();

            $props = $response->viewData('page')['props'];

            // Shared props come from the middleware and are not a page's to
            // declare; InertiaSharedPropsTest covers those.
            $sent = array_values(array_diff(
                array_keys($props),
                ['auth', 'flash', 'errors']
            ));
            sort($sent);

            $declared = $this->declaredProps($component);
            sort($declared);

            if ($sent !== $declared) {
                $mismatches[] = sprintf(
                    "%s\n      controller sends: %s\n      Props declares:   %s",
                    $component, implode(', ', $sent) ?: '(none)', implode(', ', $declared) ?: '(none)'
                );
            }
        }

        $this->assertSame([], $mismatches,
            "A page's Props interface no longer matches what its controller sends:\n  "
            .implode("\n  ", $mismatches)."\n");
    }

    /**
     * The top-level keys of `interface Props { ... }` in a page component.
     *
     * @return list<string>
     */
    private function declaredProps(string $component): array
    {
        $path = resource_path('js/Pages/'.$component.'.vue');

        $this->assertFileExists($path, "No Vue page for Inertia component {$component}.");

        $source = (string) file_get_contents($path);

        if (! preg_match('/interface\s+Props\s*\{(.*?)\n\}/s', $source, $block)) {
            $this->fail("{$component} declares no `interface Props`.");
        }

        // Top level only: skip anything nested inside a braced sub-shape.
        $depth = 0;
        $keys = [];

        foreach (explode("\n", $block[1]) as $line) {
            if ($depth === 0 && preg_match('/^\s*([A-Za-z_][\w]*)\??\s*:/', $line, $key)) {
                $keys[] = $key[1];
            }

            $depth += substr_count($line, '{') - substr_count($line, '}');
        }

        return array_values(array_unique($keys));
    }
}
```

- [ ] **Step 2: Run it and watch it fail**

```bash
php artisan test tests/Feature/InertiaPropContractTest.php
```

Expected: FAIL — `resources/js/Pages/Profile/Edit.vue` does not exist yet.
That is the correct first failure; Task 7 creates it.

- [ ] **Step 3: Commit (hand-off, after Task 7 makes it pass)**

Hold this commit until Task 7. The guard and the page it guards land together.

---

## Task 7: Convert `/profile`

The proving page: three forms, two sentinel flashes, one Alpine `{ show: true }`
toggle, and a delete dialog. If the stack works here it works generally.

**Files:**
- Create: `resources/js/Pages/Profile/Edit.vue`,
  `resources/js/Pages/Profile/Partials/UpdateProfileInformationForm.vue`,
  `resources/js/Pages/Profile/Partials/UpdatePasswordForm.vue`,
  `resources/js/Pages/Profile/Partials/DeleteUserForm.vue`
- Create: `resources/js/Components/{Card,Field,Btn,PageHeader,InputError}.vue`

**Port target.** `resources/views/profile/edit.blade.php` and its three partials
in `resources/views/profile/partials/`. Read all four first.

**Carry these over exactly:**
- `delete-account-password` as the id on the delete form's password field. Two
  fields named `password` on one page took the same id and a label pointed at
  the wrong one; `DuplicateElementIdTest` guards it.
- The sentinel matches: `status === 'profile-updated'` and
  `status === 'password-updated'` show their inline saved indicators.
  `verification-link-sent` also appears here and must keep working — the Blade
  `/verify-email` page uses the same sentinel and is not converting.
- The three named error bags (`updatePassword`, `userDeletion`, default) become
  Inertia's per-form `errors`, which are already scoped per request.

- [ ] **Step 1: Write the page with its Props interface**

```vue
<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import Card from '../../Components/Card.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import type { PageProps } from '../../types/inertia';

interface Props {
    user: {
        first_name: string;
        last_name: string;
        nickname: string | null;
        email: string;
        email_verified_at: string | null;
    };
}

const props = defineProps<Props>();
const flash = usePage<PageProps>().props.flash;
</script>

<template>
    <PageHeader eyebrow="Account" title="Profile" />

    <div class="l-container l-stack">
        <Card><UpdateProfileInformationForm :user="props.user" :status="flash.status" /></Card>
        <Card><UpdatePasswordForm :status="flash.status" /></Card>
        <Card><DeleteUserForm /></Card>
    </div>
</template>
```

Note `PageHeader` renders into the layout's `header` slot — give it a
`<template #header>` wrapper if the port needs it; match whatever
`AppLayout.vue` exposes from Task 4.

- [ ] **Step 2: Port the three partials**

Each uses `useForm` from `@inertiajs/vue3`:

```ts
import { useForm } from '@inertiajs/vue3';

const form = useForm({
    first_name: props.user.first_name,
    last_name: props.user.last_name,
    nickname: props.user.nickname ?? '',
    email: props.user.email,
});

// A literal path, not route(). Ziggy is not installed and is deliberately out
// of scope, so no route helper exists client-side -- the same reason
// Navigation.vue compares against page.url for its current state. If enough
// pages come to need named routes, adding Ziggy is its own decision, not
// something to smuggle in here.
const submit = (): void => {
    form.patch('/profile', { preserveScroll: true });
};
```

Replace Alpine's `x-data="{ show: true }"` with `const show = ref(true)`.

The three form endpoints, all as literal paths: `PATCH /profile` (update),
`PUT /password` (password), `DELETE /profile` (delete account).

- [ ] **Step 3: Register the page in the prop guard**

`InertiaPropContractTest::PAGES` already lists `'Profile/Edit' => 'profile.edit'`
from Task 6. No change needed — this step is the check that it is there.

- [ ] **Step 4: Run the two guards and the profile tests**

```bash
php artisan test tests/Feature/InertiaPropContractTest.php
php artisan test tests/Feature/ComponentParityTest.php
php artisan test tests/Feature/InertiaSharedPropsTest.php
bin/suite.sh Profile
```

Expected: all pass.

- [ ] **Step 5: Prove the prop guard bites**

Add `'stray' => true` to the props array in `ProfileController@edit`, run
`php artisan test tests/Feature/InertiaPropContractTest.php`, and expect a
failure naming `stray` as sent but not declared. Remove it and confirm green.

- [ ] **Step 6: Confirm the route-walking guards still see HTML**

Spec §6 flags this as an assumption to verify rather than trust.

```bash
php artisan test tests/Feature/NestedAnchorTest.php
php artisan test tests/Feature/DuplicateElementIdTest.php
php artisan test tests/Feature/RouteSmokeTest.php
```

Expected: all pass. An Inertia response to a non-XHR request is a full HTML
document, so these keep working. If they do not, stop — the whole test strategy
in spec §6 depends on this and needs revisiting before more pages convert.

- [ ] **Step 7: Full suite, type-check, build**

```bash
npm run typecheck && npx vite build
out=$(bin/suite.sh); status=$?; echo "$out" | tail -1; echo "exit=$status"
```

Expected: `ran 112 of 112 files, 0 failing`, exit 0. Two new test files make it
114 — confirm the count rises rather than staying put.

- [ ] **Step 8: Verify in a browser**

Serve the app and check `/profile` at 1440, 960 and 375, in both themes:
- all three cards render and are styled identically to the Blade version
- saving the profile shows the inline saved indicator (sentinel flash works)
- the delete dialog opens, and its password label focuses the field beside it
- no console errors

- [ ] **Step 9: Commit (hand-off)**

Files: `resources/js/Pages/Profile/**`, `resources/js/Components/**`,
`tests/Feature/InertiaPropContractTest.php`

```
feat: convert /profile to Inertia + Vue

The proving page: three forms, two sentinel flashes, an Alpine toggle and the
delete dialog. Keeps delete-account-password as the delete form's field id --
two fields named password on one page took the same id and a label pointed at
the wrong one.

InertiaPropContractTest lands with it: it walks each converted route, reads the
real props, and fails when they stop matching the page's Props interface, since
TypeScript cannot see what the server sends. Verified it fails on an added prop.

Confirmed NestedAnchorTest, DuplicateElementIdTest and RouteSmokeTest still see
real markup on an Inertia page -- spec §6 depends on it.
```

---

## Plans 2–4: task inventory

Written when Plan 1 lands, because each needs the foundation's actual code to
quote rather than guess at.

**Plan 2 — Shared components (est. 5–7 days).** `<Emph>` first — spec §3's
renderer for the U+2063 markers `emph()` writes, needed by the first page that
shows a real flash message rather than a sentinel. Then the remaining nine twins
from spec §2 (`btn`, `card`, `field`, `alert`, `badge`, `table`, `rank`, `monogram`,
`empty-state`, `player-link`), each added to `ComponentParityTest::TWINS` as it
is created; then the app-only components (`page-header`, `stat`, `meter`,
`season-panel`, `notification-card`, `action`, `dropdown`, `modal`, `topbar`,
`brand`, `theme-toggle`, `honeypot`, `input-error`, `auth-session-status`,
`dropdown-link`) and the pagination twin.

**Plan 3 — Pages (est. 15–20 days).** The remaining 28 routes, each registered
in `InertiaPropContractTest::PAGES`, in this order: the five index pages
(`poker/seasons`, `poker/tournaments`, `poker/venues`, `poker/points-structure`,
`users`, `sponsors`), then the create and edit forms, then the detail pages
(`players/show`, `seasons/show`, `venues/show`, `users/show`), then
`poker/tournaments/show` last at 499 lines. Each page ports its own tests from
`assertSee` to `assertInertia` as part of its task.

**Plan 4 — Guard re-homing and removal (est. 4–6 days).** Re-point the twelve
guards from spec §6 at `.vue`; retire `MarkupBalanceTest` and
`AlpineAttributeGuardTest` once their last Blade target has converted; remove
Alpine from `app.ts` and `global.d.ts`; give `AppLayout.vue` ownership of flash
once the eight duplicating views have gone and the sentinel controllers can
flash real copy.

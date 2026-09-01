# Sponsor Management Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Sponsors become a managed resource with uploaded logos, optional website links and two tiers, rendered on the home page with premium sponsors larger and first.

**Architecture:** A seventh admin CRUD resource following the six that exist, plus a `public`-disk upload following `ProfileController`'s pattern, plus a rewrite of the home page's hardcoded sponsor array to read from the database. One model scope expresses the display order so the admin list and the public wall cannot disagree.

**Tech Stack:** Laravel 12 · PHP 8.5 · SQLite · Blade · PHPUnit 11

**Spec:** `docs/superpowers/specs/2026-09-01-sponsor-management-design.md`

## Global Constraints

- **NEVER RUN GIT COMMANDS.** Every commit step is a hand-off: state the files and the message, then stop. Use `find . -newer .git/COMMIT_EDITMSG` to see what changed.
- **`Sponsor::ordered()` is the only expression of display order.** The admin index and the home page both call it.
- **No inline CSS or JavaScript.** Enforced by `InlineStyleGuardTest`.
- **Every new guard test must be proven to fail** before it is trusted.
- **Deleting a sponsor deletes an uploaded file.** Irreversible; the confirmation must say so.
- **Uploads go to the `public` disk under `sponsor-logos/`**, matching `ProfileController`'s `store('profile-images', 'public')`.

## Verification

```bash
php artisan test          # 176 passing before this plan
php artisan migrate
npm run build
```

## File structure

| File | Responsibility | Task |
|---|---|---|
| `tests/Feature/StorageLinkTest.php` | the symlink exists | 1 |
| `README.md` | `storage:link` in the documented setup | 1 |
| `database/migrations/*_create_sponsors_table.php` | the table | 2 |
| `app/Models/Sponsor.php` | `ordered()`, `isPremium()`, `logoUrl()` | 2 |
| `database/factories/SponsorFactory.php` | `premium()` state | 2 |
| `app/Http/Controllers/SponsorController.php` | CRUD + upload lifecycle | 3 |
| `routes/web.php` | the resource, in the admin group | 3 |
| `resources/views/sponsors/{index,create,edit}.blade.php` | admin screens | 3 |
| `resources/views/layouts/navigation.blade.php` | Sponsors under Setup | 3 |
| `resources/views/home.blade.php` | the wall, from the database | 4 |
| `resources/css/5-public/_register.css` | the premium card | 4 |
| `tests/Feature/SponsorTest.php` | model, CRUD, uploads, the wall | 2, 3, 4 |

---

### Task 1: Make the public disk actually reachable

**Files:** `tests/Feature/StorageLinkTest.php` (new), `README.md`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The public disk is only reachable over HTTP through public/storage, and its
 * absence is INVISIBLE: nothing errors, an uploaded file simply 404s and the
 * page shows a broken image. Avatars have been written to that disk since
 * before this feature and the gap went unnoticed only because no user has ever
 * uploaded one. Sponsor logos would hit it on the first upload.
 */
class StorageLinkTest extends TestCase
{
    public function test_the_public_storage_link_exists(): void
    {
        $this->assertTrue(
            file_exists(public_path('storage')),
            'public/storage is missing. Run `php artisan storage:link` — without it every uploaded '
            .'file 404s and the only symptom is a broken image.'
        );
    }

    public function test_the_link_points_at_the_public_disk(): void
    {
        // A stale or hand-made link pointing somewhere else fails the same way
        // as a missing one, and looks fine in a directory listing.
        $this->assertSame(
            realpath(storage_path('app/public')),
            realpath(public_path('storage')),
            'public/storage does not resolve to storage/app/public.'
        );
    }
}
```

- [ ] **Step 2: Run it and watch it fail**

`php artisan test --filter=StorageLinkTest` — both fail; the link does not exist.

- [ ] **Step 3: Create the link**

```bash
php artisan storage:link
```

- [ ] **Step 4: Run it again — both pass**

- [ ] **Step 5: Put it in the documented setup**

`README.md` describes `composer setup`. Check whether that script already runs
`storage:link`:

```bash
grep -n "storage:link\|\"setup\"" composer.json README.md
```

If it does not, add `@php artisan storage:link` to the `setup` script in
`composer.json` so a fresh clone gets it. **`public/storage` is gitignored**
(check `.gitignore`), which is why it must be created by setup rather than
committed.

- [ ] **Step 6: HAND-OFF**

```
fix: create the public storage link, and test that it exists

Anything written to the public disk is only reachable over HTTP through
public/storage, and the link was never created. Nothing errors when it is
missing -- an uploaded file simply 404s and the page shows a broken image.

This predates the sponsor work: ProfileController has stored avatars to that
disk all along, and the gap went unnoticed only because no user has ever
uploaded one. Sponsor logos would have hit it on the first upload.

The test covers a stale link as well as a missing one, since a link pointing
somewhere else fails identically and looks fine in a directory listing.
```

---

### Task 2: The Sponsor model

**Files:** migration, `app/Models/Sponsor.php`, `database/factories/SponsorFactory.php`, `tests/Feature/SponsorTest.php`

**Interfaces produced:** `Sponsor::ordered()`, `Sponsor::isPremium(): bool`, `Sponsor::logoUrl(): string`, factory state `premium()`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Sponsor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SponsorTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_sponsor_defaults_to_the_regular_tier(): void
    {
        $sponsor = Sponsor::create(['name' => 'Ace High', 'logo_path' => 'sponsor-logos/a.png']);

        $this->assertSame('regular', $sponsor->fresh()->tier);
        $this->assertFalse($sponsor->isPremium());
    }

    public function test_ordered_puts_premium_first_then_sort_order_then_name(): void
    {
        // The single expression of display order. The admin list and the public
        // wall both call it, so they cannot disagree about what "first" means.
        Sponsor::create(['name' => 'Zed Regular', 'logo_path' => 'x.png', 'sort_order' => 1]);
        Sponsor::create(['name' => 'Abe Regular', 'logo_path' => 'x.png', 'sort_order' => 1]);
        Sponsor::create(['name' => 'Early Regular', 'logo_path' => 'x.png', 'sort_order' => 0]);
        Sponsor::create(['name' => 'Prem', 'logo_path' => 'x.png', 'tier' => 'premium', 'sort_order' => 9]);

        $this->assertSame(
            ['Prem', 'Early Regular', 'Abe Regular', 'Zed Regular'],
            Sponsor::ordered()->pluck('name')->all()
        );
    }

    public function test_logo_url_points_at_the_public_disk(): void
    {
        $sponsor = Sponsor::create(['name' => 'Ace High', 'logo_path' => 'sponsor-logos/a.png']);

        $this->assertStringContainsString('/storage/sponsor-logos/a.png', $sponsor->logoUrl());
    }
}
```

Note the premium sponsor has the **highest** `sort_order` and must still come
first: tier outranks order, and a test where premium also sorted first by
accident would prove nothing.

- [ ] **Step 2: Run it and watch it fail**

- [ ] **Step 3: The migration**

`php artisan make:migration create_sponsors_table`, then:

```php
Schema::create('sponsors', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->string('name');
    $table->string('logo_path');
    $table->string('website_url')->nullable();
    // A string, not an is_premium boolean: the brief names two tiers, but a
    // third ("Founding", "Venue partner") is the kind of thing a league adds,
    // and a boolean cannot grow into one.
    $table->string('tier')->default('regular')->index();
    // Ordering within a tier. Without it the wall is ordered by insertion, so
    // moving a sponsor means deleting and re-adding -- and since the logo is
    // required, that means re-uploading the artwork.
    $table->integer('sort_order')->default(0);
    $table->timestamps();
});
```

- [ ] **Step 4: The model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Sponsor extends Model
{
    /** @use HasFactory<\Database\Factories\SponsorFactory> */
    use HasFactory, HasUlids;

    protected $fillable = ['name', 'logo_path', 'website_url', 'tier', 'sort_order'];

    // The schema defaults this, but a database default is applied on INSERT and
    // never hydrated back, so a freshly created instance would report no tier
    // at all until it was reloaded.
    protected $attributes = ['tier' => 'regular', 'sort_order' => 0];

    /**
     * Display order, defined once. The admin index and the public wall both
     * call this, so they cannot disagree about what comes first.
     */
    public function scopeOrdered($query)
    {
        return $query->orderByRaw("CASE WHEN tier = 'premium' THEN 0 ELSE 1 END")
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function isPremium(): bool
    {
        return $this->tier === 'premium';
    }

    public function logoUrl(): string
    {
        return Storage::disk('public')->url($this->logo_path);
    }
}
```

- [ ] **Step 5: The factory**

```php
public function definition(): array
{
    return [
        'name' => fake()->company(),
        'logo_path' => 'sponsor-logos/placeholder.png',
        'website_url' => null,
        'tier' => 'regular',
        'sort_order' => 0,
    ];
}

public function premium(): static
{
    return $this->state(fn () => ['tier' => 'premium']);
}
```

- [ ] **Step 6: Migrate and test**

- [ ] **Step 7: HAND-OFF**

```
feat(sponsors): add the Sponsor model

tier is a string rather than an is_premium boolean: the brief names two
tiers, but a third is the kind of thing a league adds and a boolean cannot
grow into one. sort_order is not in the brief and is included because
without it the wall is ordered by insertion, so moving a sponsor would mean
deleting and re-adding it -- and since the logo is required, re-uploading
the artwork.

ordered() is the single expression of display order; the admin list and the
public wall both call it so they cannot disagree.

The tier is defaulted on the model as well as in the schema. A database
default is applied on insert and never hydrated back, so a freshly created
instance would report no tier at all until it was reloaded.
```

---

### Task 3: Admin CRUD

**Files:** `app/Http/Controllers/SponsorController.php`, `routes/web.php`, three views, `navigation.blade.php`, `tests/Feature/SponsorTest.php`

- [ ] **Step 1: Write the failing tests**

Cover: create with an uploaded logo; logo required; oversized/non-image rejected;
update replacing a logo deletes the old file; delete removes the file; a player
gets 403. Use `Storage::fake('public')` and `UploadedFile::fake()->image()`.

```php
    public function test_creating_a_sponsor_stores_the_logo(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('sponsors.store'), [
            'name' => 'Ace High',
            'logo' => UploadedFile::fake()->image('ace.png'),
            'tier' => 'premium',
        ])->assertRedirect();

        $sponsor = Sponsor::first();
        $this->assertNotNull($sponsor);
        Storage::disk('public')->assertExists($sponsor->logo_path);
    }

    public function test_replacing_a_logo_deletes_the_old_file(): void
    {
        // Otherwise every edit leaves an orphan on disk that nothing will ever
        // reference or clean up.
        Storage::fake('public');
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('sponsors.store'), [
            'name' => 'Ace High',
            'logo' => UploadedFile::fake()->image('first.png'),
        ]);

        $sponsor = Sponsor::first();
        $original = $sponsor->logo_path;

        $this->actingAs($admin)->put(route('sponsors.update', $sponsor), [
            'name' => 'Ace High',
            'logo' => UploadedFile::fake()->image('second.png'),
        ]);

        Storage::disk('public')->assertMissing($original);
        Storage::disk('public')->assertExists($sponsor->fresh()->logo_path);
    }
```

- [ ] **Step 2: Run them and watch them fail**

- [ ] **Step 3: The controller**

Follow `VenueController`'s shape. Validation:

```php
'name' => ['required', 'string', 'max:255'],
'website_url' => ['nullable', 'url', 'max:255'],
'tier' => ['required', 'in:premium,regular'],
'sort_order' => ['nullable', 'integer', 'min:0'],
// Required on store, optional on update -- an edit that changes only the
// name must not demand the artwork again.
'logo' => [$isUpdate ? 'nullable' : 'required', 'image', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
```

On store and update, when a file is present: `store('sponsor-logos', 'public')`,
then delete the previous path. On destroy, delete the file.

- [ ] **Step 4: Routes**

Inside the existing admin group beside `users`:

```php
Route::resource('sponsors', \App\Http\Controllers\SponsorController::class)->except(['show']);
```

- [ ] **Step 5: Views and nav**

Index, create and edit modelled on `poker/venues/*`. The create/edit forms need
`enctype="multipart/form-data"` — **without it the file silently never
arrives**, and validation reports a missing logo with no clue why.

Add to `navigation.blade.php` under Setup:

```blade
<x-dropdown-link :href="route('sponsors.index')">{{ __('Sponsors') }}</x-dropdown-link>
```

- [ ] **Step 6: Test, then add a sponsor through the real UI**

- [ ] **Step 7: HAND-OFF**

---

### Task 4: The public wall

**Files:** `resources/views/home.blade.php`, `resources/css/5-public/_register.css`, `app/Http/Controllers/HomeController.php` (or wherever home is rendered), `tests/Feature/SponsorTest.php`

- [ ] **Step 1: Write the failing tests**

```php
    public function test_the_home_page_renders_sponsors_from_the_database(): void
    {
        Sponsor::factory()->create(['name' => 'Regularly']);
        Sponsor::factory()->premium()->create(['name' => 'Premiumly']);

        $response = $this->get('/');

        $response->assertOk()->assertSee('Premiumly')->assertSee('Regularly');
        // Premium first, in the rendered markup rather than in a collection.
        $this->assertLessThan(
            strpos($response->getContent(), 'Regularly'),
            strpos($response->getContent(), 'Premiumly')
        );
    }

    public function test_the_sponsor_section_is_absent_when_there_are_none(): void
    {
        // A "Proudly Supported By" heading over an empty grid advertises that
        // nobody sponsors the league.
        $this->get('/')->assertOk()->assertDontSee('Proudly Supported By');
    }

    public function test_a_sponsor_with_a_website_links_out_safely(): void
    {
        Sponsor::factory()->create(['name' => 'Linked', 'website_url' => 'https://example.com']);

        $this->get('/')->assertOk()->assertSee('rel="noopener noreferrer"', false);
    }
```

- [ ] **Step 2: Run them and watch them fail**

- [ ] **Step 3: Pass sponsors to the view**

Find where `/` is rendered (`grep -n "home" routes/web.php`) and add
`Sponsor::ordered()->get()`.

- [ ] **Step 4: Rewrite the wall**

Delete the hardcoded `@php $sponsors = [...]` array. Render from the collection,
wrapping the whole section in `@if ($sponsors->isNotEmpty())`. Each card links
out when `website_url` is set, with `target="_blank" rel="noopener noreferrer"`
and an accessible name that says the link leaves the site. The logo's `alt` is
the sponsor name — the logo *is* the content, not decoration.

- [ ] **Step 5: The premium card**

```css
/* Premium sponsors take two columns where two exist. Below that the grid is
   already single-column and the span is a no-op, which is why this needed no
   mobile special case. */
@media (min-width: 40rem) {
    .p-sponsor--premium {
        grid-column: span 2;
    }
}
```

- [ ] **Step 6: Test, build, screenshot both themes**

- [ ] **Step 7: HAND-OFF**

---

### Task 5: Audit

- [ ] Full suite and build
- [ ] Add a premium and a regular sponsor through the UI, with real images
- [ ] Confirm the logo is reachable (not a broken image) — this is what the storage link was for
- [ ] Confirm the external link opens correctly and carries `rel="noopener noreferrer"`
- [ ] Delete a sponsor; confirm the file is gone from `storage/app/public/sponsor-logos`
- [ ] Guard tests: `ConvertedViewsTest`, `InlineStyleGuardTest`, `PublicRegisterTest`, `ModifierClassGuardTest`
- [ ] Screenshot the wall and the admin index in both themes
- [ ] Write `docs/SPONSOR-MANAGEMENT-AUDIT.md`; update `docs/RESUME-HERE.md`

## Out of scope

- Drag-and-drop reordering; `sort_order` is a number on the form.
- Sponsor logos anywhere but the home page.
- Click tracking.
